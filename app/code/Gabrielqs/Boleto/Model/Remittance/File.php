<?php

namespace Gabrielqs\Boleto\Model\Remittance;

use \Magento\Framework\Model\AbstractModel;
use \Magento\Framework\DataObject\IdentityInterface;
use \Magento\Framework\Model\Context;
use \Magento\Framework\Registry;
use \Magento\Framework\Model\ResourceModel\AbstractResource;
use \Magento\Framework\Data\Collection\AbstractDb;
use \Magento\Framework\Api\SearchCriteriaBuilder;
use \Magento\Sales\Model\OrderRepository as SalesOrderRepository;
use \Magento\Sales\Model\Order as SalesOrder;
use \Magento\Framework\Filesystem;
use \Magento\Framework\App\Filesystem\DirectoryList;
use \Gabrielqs\Boleto\Api\Data\RemittanceFileInterface;
use \Gabrielqs\Boleto\Model\Remittance\File\EventRepository;
use \Gabrielqs\Boleto\Model\Remittance\File\EventFactory;
use \Gabrielqs\Boleto\Model\Remittance\File\OrderRepository;
use \Gabrielqs\Boleto\Model\Remittance\File\OrderFactory;
use \Gabrielqs\Boleto\Api\Data\RemittanceFileEventSearchResultsInterface;
use \Gabrielqs\Boleto\Api\Data\RemittanceFileOrderSearchResultsInterface;
use \Gabrielqs\Boleto\Api\Data\RemittanceFileEventInterface;
use \Gabrielqs\Boleto\Api\Data\RemittanceFileOrderInterface;

/**
 * Class File
 * @package Gabrielqs\Boleto\Model\Remittance
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class File extends AbstractModel implements RemittanceFileInterface, IdentityInterface
{
    /**
     * Cache tag
     */
    const CACHE_TAG = 'boleto_remittance_file';

    /**
     * Status - New
     */
    const STATUS_NEW = 1;

    /**
     * Status - Error
     */
    const STATUS_ERROR = 2;

    /**
     * Status - Success
     */
    const STATUS_SUCCESS = 3;

    /**
     * Event Collection
     * @var RemittanceFileEventSearchResultsInterface|null
     */
    protected $_eventCollection = null;

    /**
     * Event Factory
     * @var EventFactory|null
     */
    protected $_eventFactory = null;

    /**
     * Event Repository
     * @var EventRepository|null
     */
    protected $_eventRepository = null;

    /**
     * File System
     * @var Filesystem $fileSystem
     */
    protected $fileSystem = null;

    /**
     * Order Collection
     * @var RemittanceFileOrderSearchResultsInterface|null
     */
    protected $_orderCollection = null;

    /**
     * Order Factory
     * @var OrderFactory|null
     */
    protected $_orderFactory = null;

    /**
     * Order Repository
     * @var OrderRepository|null
     */
    protected $_orderRepository = null;

    /**
     * Sales Order Repository
     * @var SalesOrderRepository|null
     */
    protected $_salesOrderRepository = null;

    /**
     * Search Criteria Builder
     * @var SearchCriteriaBuilder|null
     */
    protected $_searchCriteriaBuilder = null;

    /**
     * Storage Folder Name
     * @var string
     */
    protected $_storageFolderName = '/boletoremittancefiles/';

    /**
     * Remittance File constructor.
     * @param Context $context
     * @param Registry $registry
     * @param EventRepository $eventRepository
     * @param EventFactory $eventFactory
     * @param OrderRepository $orderRepository
     * @param OrderFactory $orderFactory
     * @param SalesOrderRepository $salesOrderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Filesystem $fileSystem
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        EventRepository $eventRepository,
        EventFactory $eventFactory,
        OrderRepository $orderRepository,
        OrderFactory $orderFactory,
        SalesOrderRepository $salesOrderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Filesystem $fileSystem,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $return = parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->_eventRepository = $eventRepository;
        $this->_eventFactory = $eventFactory;
        $this->_orderRepository = $orderRepository;
        $this->_orderFactory = $orderFactory;
        $this->_salesOrderRepository = $salesOrderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->fileSystem = $fileSystem;


        return $return;
    }

    /**
     * Remittance File Initialization
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Gabrielqs\Boleto\Model\ResourceModel\Remittance\File');
    }

    /**
     * Creates a new remittance file event
     * @param string $description
     * @return RemittanceFileEventInterface|null
     */
    public function createNewEvent($description)
    {
        $event = null;
        if ($this->getId()) {
            $event = $this->_eventFactory->create();
            $event
                ->setRemittanceFileId($this->getId())
                ->setDescription($description);
            $this->_eventRepository->save($event);
        }
        return $event;
    }

    /**
     * Creates a new remittance file order
     * @param integer $orderId
     * @return RemittanceFileOrderInterface|null
     */
    public function createNewOrderById($orderId)
    {
        $order = null;
        if ($this->getId()) {
            $searchCriteria = $this
                ->_searchCriteriaBuilder
                ->addFilter(SalesOrder::ENTITY_ID, $orderId, 'eq')
                ->create();
            $salesOrderList = $this->_salesOrderRepository->getList($searchCriteria);
            if ($salesOrderList->getTotalCount()) {
                $salesOrder = $salesOrderList->getFirstItem();
                $salesOrderId = $salesOrder->getId();
                $order = $this->_orderFactory->create();
                $order
                    ->setOrderId($salesOrderId)
                    ->setRemittanceFileId($this->getId());
                $this->_orderRepository->save($order);
            }
        }
        return $order;
    }

    /**
     * Reads file contents from file system and remittance it
     * @return string
     */
    public function getContents()
    {
        return file_get_contents($this->getPath());
    }

    /**
     * Get Creation Time
     * @return string|null
     */
    public function getCreationTime()
    {
        return $this->getData(self::CREATION_TIME);
    }

    /**
     * Remittance a collection with all events related to this Remittance File
     * @return RemittanceFileEventSearchResultsInterface
     */
    public function getEventCollection()
    {
        if ($this->_eventCollection === null) {
            $searchCriteria = $this
                ->_searchCriteriaBuilder
                ->addFilter(RemittanceFileEventInterface::REMITTANCE_FILE_ID, $this->getId(), 'eq')
                ->create();
            $eventCollection = $this->_eventRepository->getList($searchCriteria);
            $this->_eventCollection = $eventCollection;
        }
        return $this->_eventCollection;
    }

    /**
     * Get ID
     * @return int|null
     */
    public function getId()
    {
        return $this->getData(self::REMITTANCE_FILE_ID);
    }

    /**
     * Return identities
     * @return string[]
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Get Name
     * @return string|null
     */
    public function getName()
    {
        return $this->getData(self::NAME);
    }

    /**
     * Returns a collection with all orders related to this Remittance File
     * @return RemittanceFileOrderSearchResultsInterface
     */
    public function getOrderCollection()
    {
        if ($this->_orderCollection === null) {
            $searchCriteria = $this
                ->_searchCriteriaBuilder
                ->addFilter(RemittanceFileOrderInterface::REMITTANCE_FILE_ID, $this->getId(), 'eq')
                ->create();
            $eventCollection = $this->_orderRepository->getList($searchCriteria);
            $this->_orderCollection = $eventCollection;
        }
        return $this->_orderCollection;
    }

    /**
     * Returns folder where all remittance files are stored
     * @return string
     */
    public function getStoragePath()
    {
        return $this
            ->fileSystem
            ->getDirectoryWrite(DirectoryList::VAR_DIR)
            ->getAbsolutePath($this->_storageFolderName);
    }

    /**
     * Remittance full path for the currently loaded remittance file
     * @return string
     */
    public function getPath()
    {
        return $this->getStoragePath() . '/' . $this->getName();
    }

    /**
     * Get Status
     * @return integer|null
     */
    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    /**
     * Get Update Time
     * @return string|null
     */
    public function getUpdateTime()
    {
        return $this->getData(self::UPDATE_TIME);
    }

    /**
     * Saves file contents to file system
     * @param string $contents
     * @return $this
     */
    public function saveFileToFileSystem($contents)
    {
        $writer = $this->fileSystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $file = $writer->openFile($this->_storageFolderName . $this->getName(), 'w');
        $file->lock();
        $file->write($contents);
        $file->unlock();
        $file->close();
        return $this;
    }

    /**
     * Set Creation Time
     * @param string $creationTime
     * @return $this
     */
    public function setCreationTime($creationTime)
    {
        return $this->setData(self::CREATION_TIME, $creationTime);
    }

    /**
     * Set ID
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        return $this->setData(self::REMITTANCE_FILE_ID, $id);
    }

    /**
     * Set Name
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * Set Status
     * @param int $status
     * @return $this
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * Set Update Time
     * @param string $updateTime
     * @return $this
     */
    public function setUpdateTime($updateTime)
    {
        return $this->setData(self::UPDATE_TIME, $updateTime);
    }
}
