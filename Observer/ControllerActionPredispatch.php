<?php
namespace Mss\Connector\Observer;

use \Magento\Framework\Event\Observer;
use \Magento\Framework\Event\ObserverInterface;

class ControllerActionPredispatch implements ObserverInterface
{
    const XML_SECURE_KEY        = 'magentomobileshop/secure/key';
    const ACTIVATION_URL        = 'https://www.magentomobileshop.com/user/mss_verifiy';
    const TRNS_EMAIL            = 'trans_email/ident_general/email';
    const XML_SECURE_KEY_STATUS = 'magentomobileshop/key/status';
    private $logger;
    private $response;
    //@codingStandardsIgnoreStart
    public function __construct(
        \Psr\Log\LoggerInterface $loggerInterface,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Backend\App\Action $action,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\Resolver $resolver,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Response\Http $response,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
         
        $this->logger                = $loggerInterface;
        $this->urlInterface          = $urlInterface;
        $this->action                = $action;
        $this->coreRegistry          = $coreRegistry;
        $this->scopeConfig           = $scopeConfig;
        $this->resolver              = $resolver;
        $this->resourceConfig        = $resourceConfig;
        $this->storeManager          = $storeManager;
        $this->cacheTypeList         = $cacheTypeList;
        $this->response              = $response;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->responseFactory       = $responseFactory;
        $this->messageManager        = $messageManager;
    }
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $adminsession      = \Magento\Security\Model\AdminSessionInfo::LOGGED_IN;
        $url               = $this->urlInterface->getCurrentUrl();
        $objectData        = \Magento\Framework\App\ObjectManager::getInstance();
        $this->request     = $objectData->create('\Magento\Framework\App\Request\Http');
        $decode            = $this->request->getParam('mms_id');
        $mssAppData        = ''; 
        $this->authSession = $objectData->create('\Magento\Backend\Model\Auth\Session');
        $this->coreSession = $objectData->create('\Magento\Backend\Model\Session');

        if ($decode and !$this->coreRegistry->registry('mms_app_data')) {
            $param = base64_decode($decode);
            $this->coreRegistry->register('mms_app_data', $param);
            $mssAppData = $this->coreRegistry->registry('mms_app_data');
        }
        $current = $this->scopeConfig->getValue('magentomobileshop/secure/key');

        if (!$this->scopeConfig->getValue(self::XML_SECURE_KEY) and $adminsession) {
            $static_url = 'https://www.magentomobileshop.com/user/buildApp?key_info=';
            $email      = base64_encode($this->scopeConfig->getValue(self::TRNS_EMAIL));
            $url        = base64_encode($this->storeManager->getStore()->getBaseUrl());
            $key        = base64_encode('email=' . $email . '&url=' . $url);
            //@codingStandardsIgnoreEnd
            $href       = $static_url . $key;
            $this->messageManager->addNotice(__('Magentomobileshop
                extension is not activated yet, <a href="' . $href . '">
                Click here</a> to activate your extension.'));
        }

        if ((!$current) and $adminsession and $mssAppData != '') {
            if ((!$current)) {
                $str        = self::ACTIVATION_URL;
                $url        = $str . '?mms_id=';
                $final_url  = $url . '' . $mssAppData;
                $final_urls = $str;
                $this->resourceConfig->saveConfig(self::XML_SECURE_KEY, $mssAppData, 'default', 0);
                $this->resourceConfig->saveConfig(self::XML_SECURE_KEY_STATUS, '1', 'default', 0);
                $lang                             = $this->resolver->getLocale();
                $mssData                          = [];
                $mssData[0]['mms_id']             = base64_encode($mssAppData);
                $mssData[0]['default_store_name'] = $this->storeManager->getStore()->getCode();
                $mssData[0]['default_store_id']   = $this->storeManager
                    ->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();
                $mssData[0]['default_view_id']        = $this->storeManager->getStore()->getId();
                $mssData[0]['default_store_currency'] = $this->storeManager->getStore()->getCurrentCurrencyCode();
                $mssData[0]['language']               = $lang;
                $mssData[0]['status']                 = 'true';
                $this->cacheTypeList->cleanType('config');
                $this->coreSession->setAppDatas($mssData[0]);
                $this->coreRegistry->unregister('mms_app_data');
                $customerBeforeAuthUrl = $this->urlInterface
                    ->getUrl('mss_connector/system_connector/index');
                $this->responseFactory->create()->setRedirect($customerBeforeAuthUrl)->sendResponse();
            } elseif ($current != '' and $adminsession->isLoggedIn() and $decode != '') {
                $str        = self::ACTIVATION_URL;
                $url        = $str . '?mms_id=';
                $final_url  = $url . '' . $mssAppData;
                $final_urls = $str;
                $this->resourceConfig->saveConfig(self::XML_SECURE_KEY, $mssAppData);
                $this->resourceConfig->saveConfig(self::XML_SECURE_KEY_STATUS, '1');
                $mssData[0]['mms_id']             = base64_encode($mssAppData);
                $mssData[0]['default_store_name'] = $this->storeManager->getStore()->getCode();
                $mssData[0]['default_store_id']   = $this->storeManager
                    ->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();
                $mssData[0]['default_view_id']        = $this->storeManager->getStore()->getId();
                $mssData[0]['default_store_currency'] = $this->storeManager->getStore()->getCurrentCurrencyCode();
                $mssData[0]['status']                 = 'true';

                $this->cacheTypeList->cleanType('config');
                $this->coreSession->setAppDatas($mssData[0]);
                $this->coreRegistry->unregister('mms_app_data');
                $customerBeforeAuthUrl = $this->urlInterface
                    ->getUrl('mss_connector/system_connector/index');
                $this->responseFactory->create()->setRedirect($customerBeforeAuthUrl)->sendResponse();
            }
        }
    }
}
