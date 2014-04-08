<?php
namespace Svea;

require_once SVEA_REQUEST_DIR . '/WebServiceRequests/svea_soap/SveaSoapConfig.php';
require_once SVEA_REQUEST_DIR . '/Config/SveaConfig.php';

/**
 * Retrieves information about all the campaigns that are associated with the
 * current Client. Use this information to display information about the possible
 * payment plan options to customers. The returned CampaignCode is used when
 * creating a PaymentPlan order.
 * 
 * TODO document attributes
 * 
 * @author Anneli Halld'n, Daniel Brolund for Svea Webpay
 */
class GetPaymentPlanParams {

    public $testmode = false;
    public $object;
    public $conf;
    public $countryCode;

    function __construct($config) {
        $this->conf = $config;
    }

    /*
     * @param string $countryCodeAsString
     * @return $this
     */
    public function setCountryCode($countryCodeAsString) {
        $this->countryCode = $countryCodeAsString;
        return $this;
    }

    /**
     * @return SveaRequest
     */
    public function prepareRequest() {
        $auth = new SveaAuth( 
            $this->conf->getUsername(\ConfigurationProvider::PAYMENTPLAN_TYPE,  $this->countryCode),
            $this->conf->getPassword(\ConfigurationProvider::PAYMENTPLAN_TYPE,  $this->countryCode),   
            $this->conf->getClientNumber(\ConfigurationProvider::PAYMENTPLAN_TYPE,  $this->countryCode)   
        );

        $object = new SveaRequest();
        $object->request = (object) array("Auth" => $auth);

        return $object;
    }
    
    /**
     * Prepares and sends request
     * @return PaymentPlanParamsResponse
     */
    public function doRequest() {
        $requestObject = $this->prepareRequest();
        $url = $this->conf->getEndPoint(\ConfigurationProvider::PAYMENTPLAN_TYPE);
        $request = new SveaDoRequest($url);
        $response = $request->GetPaymentPlanParamsEu($requestObject);

        $responseObject = new \SveaResponse($response,"");
        return $responseObject->response;
    }  
}
