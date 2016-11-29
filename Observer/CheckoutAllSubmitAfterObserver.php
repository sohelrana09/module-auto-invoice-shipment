<?php
namespace SR\AutoInvoiceShipment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CheckoutAllSubmitAfterObserver implements ObserverInterface
{
    /**
     *
     * @var \SR\AutoInvoiceShipment\Helper\Data
     */
    protected $helper;

    /**
     * @param \SR\AutoInvoiceShipment\Helper\Data $helper
     */
    public function __construct(
        \SR\AutoInvoiceShipment\Helper\Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     *
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        if(!$this->helper->isEnabled()) {
            return $this;
        }

        $order = $observer->getEvent()->getOrder();
        if(!$order->getId()) {
            return $this;
        }

        $invoice = $this->helper->createInvoice($order);
        if($invoice) {
            $this->helper->createShipment($order, $invoice);
        }

        return $this;
    }
}