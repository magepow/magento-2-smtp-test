<?php
declare(strict_types=1);

namespace Magepow\Smtptest\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestConnection extends Field
{
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setData([
            'id' => 'magepow_smtptest_button',
            'label' => __('Test Connection'),
            'onclick' => 'return false;',
            'class' => 'action-default',
            'type' => 'button',
        ]);
        $html = $button->toHtml();
        $html .= '<div id="magepow-smtptest-result" style="margin-top:10px;max-width:600px;"></div>';
        $html .= '<script type="text/x-magento-init">{"*":{"Magepow_Smtptest/js/test-connection":'
            . '{"ajaxUrl":"' . $this->escapeUrl($this->getUrl('magepow_smtptest/smtp/test')) . '",'
            . '"buttonId":"magepow_smtptest_button","resultId":"magepow-smtptest-result"'
            . '}}}</script>';
        return $html;
    }
}

