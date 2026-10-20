<?php
declare(strict_types=1);
namespace Magepow\Smtptest\Controller\Adminhtml\Smtp;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
class Test extends Action
{
    const ADMIN_RESOURCE = 'Magento_Config::config_system';
    private $resultJsonFactory;
    private $transportBuilder;
    private $inlineTranslation;
    private $scopeConfig;
    private $storeManager;
    public function __construct(Context $context, JsonFactory $resultJsonFactory, TransportBuilder $transportBuilder, StateInterface $inlineTranslation, ScopeConfigInterface $scopeConfig, StoreManagerInterface $storeManager)
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $testEmail = trim((string)$this->getRequest()->getParam('test_email', ''));
        if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            return $result->setData(['success' => false, 'message' => __('Please enter a valid test email address.'), 'detail' => __('Given value "%1" is not a valid email.', $testEmail)]);
        }
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = null;
        $storeId = $this->getRequest()->getParam('store');
        $websiteId = $this->getRequest()->getParam('website');
        if ($storeId !== null && $storeId !== '') {
            try { $store = $this->storeManager->getStore($storeId); $scope = ScopeInterface::SCOPE_STORE; $scopeId = (int)$store->getId(); }
            catch (\Exception $e) { $scope = ScopeInterface::SCOPE_STORE; $scopeId = (int)$storeId; }
        } elseif ($websiteId !== null && $websiteId !== '') {
            try { $website = $this->storeManager->getWebsite($websiteId); $scope = ScopeInterface::SCOPE_WEBSITE; $scopeId = (int)$website->getId(); }
            catch (\Exception $e) { $scope = ScopeInterface::SCOPE_WEBSITE; $scopeId = is_numeric($websiteId) ? (int)$websiteId : null; }
        }
        $disabled = $this->scopeConfig->getValue('system/smtp/disable', $scope, $scopeId);
        $from = $this->getSender($scope, $scopeId);
        $this->inlineTranslation->suspend();
        try {
            $storeIdForEmail = $scopeId;
            if ($scope !== ScopeInterface::SCOPE_STORE) {
                try { $storeIdForEmail = (int)$this->storeManager->getDefaultStoreView()->getId(); }
                catch (\Exception $e) { $storeIdForEmail = 0; }
            }
            $transport = $this->transportBuilder->setTemplateIdentifier('magepow_smtptest_test_email')->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND, 'store' => $storeIdForEmail])->setTemplateVars(['store' => $this->storeManager->getStore($storeIdForEmail), 'test_time' => date('Y-m-d H:i:s')])->setFromByScope($from, $scope, $scopeId)->addTo($testEmail)->getTransport();
            $transport->sendMessage();
            $this->inlineTranslation->resume();
            $msg = __('Test email sent successfully to "%1".', $testEmail);
            if ($disabled) { $msg .= ' ' . __('Note: Disable Email Communications is Yes, real emails may be blocked.'); }
            return $result->setData(['success' => true, 'message' => $msg]);
        } catch (\Throwable $e) {
            $this->inlineTranslation->resume();
            $detail = $this->buildChain($e) . $this->getConfigHint($scope, $scopeId);
            return $result->setData(['success' => false, 'message' => __('Unable to send test email. Please check Mail Sending Settings.'), 'detail' => $detail]);
        }
    }
    private function getSender($scope, $scopeId): array
    {
        foreach (['general', 'sales', 'support', 'custom1', 'custom2'] as $code) {
            $name = $this->scopeConfig->getValue('trans_email/ident_' . $code . '/name', $scope, $scopeId);
            $email = $this->scopeConfig->getValue('trans_email/ident_' . $code . '/email', $scope, $scopeId);
            if ($name && $email && filter_var($email, FILTER_VALIDATE_EMAIL)) { return ['name' => $name, 'email' => $email]; }
        }
        return ['name' => 'Store Owner', 'email' => 'owner@example.com'];
    }
    private function buildChain($e): string
    {
        $out = '';
        $i = 0;
        while ($e) {
            $out .= ($i ? "\nCaused by: " : '') . get_class($e) . ': ' . $e->getMessage();
            $e = $e->getPrevious();
            if (++$i > 5) {
                break;
            }
        }
        // Common hint for this generic message
        if (strpos($out, 'Unable to send mail at this time') !== false) {
            $out .= "\n(Hint: check exception.log / debug.log for 'Transport error while sending email' "
                . '— the real SMTP error (auth failed, connection refused, TLS...) is logged there. '
                . 'Also verify host/port/SSL/Auth match your provider.)';
        }
        return $out;
    }

    private function getConfigHint($scope, $scopeId): string
    {
        $parts = [];
        foreach (['disable', 'transport', 'host', 'port', 'auth', 'ssl', 'username'] as $k) {
            $v = $this->scopeConfig->getValue('system/smtp/' . $k, $scope, $scopeId);
            $parts[] = $k . '=' . ($v === null || $v === '' ? '(empty)' : (string)$v);
        }
        return "\n\n[system/smtp " . $scope . ($scopeId !== null ? '/' . $scopeId : '') . '] ' . implode(', ', $parts);
    }
}
