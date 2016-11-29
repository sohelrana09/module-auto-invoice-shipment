<?php
namespace SR\AutoInvoiceShipment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    const MODULE_ENABLED = 'sr_auto_invoice_shipment/settings/enabled';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     *
     * @var \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory
     */
    protected $invoiceCollectionFactory;

    /**
     *
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     *
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $shipmentFactory;

    /**
     *
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->shipmentFactory = $shipmentFactory;
        $this->transactionFactory = $transactionFactory;
    }

    public function createInvoice($order)
    {
        try {
            $invoices = $this->invoiceCollectionFactory->create()
                ->addAttributeToFilter('order_id', array('eq' => $order->getId()));

            $invoices->getSelect()->limit(1);

            if ((int)$invoices->count() !== 0) {
                return null;
            }

            if(!$order->canInvoice()) {
                return null;
            }

            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addStatusHistoryComment('Automatically INVOICED', false);
            $transactionSave = $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();
        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), false);
            $order->save();
            return null;
        }

        return $invoice;
    }

    public function createShipment($order, $invoice)
    {
        try {
            $shipment = $this->prepareShipment($invoice);
            if ($shipment) {
                $order->setIsInProcess(true);
                $order->addStatusHistoryComment('Automatically SHIPPED', false);
                $this->transactionFactory->create()->addObject($shipment)->addObject($shipment->getOrder())->save();
            }
        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), false);
            $order->save();
        }
    }

    public function prepareShipment($invoice)
    {
        $shipment = $this->shipmentFactory->create(
            $invoice->getOrder(),
            []
        );

        return $shipment->getTotalQty() ? $shipment->register() : false;
    }

    /**
     * Is the module enabled in configuration.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->getValue(self::MODULE_ENABLED);
    }
}