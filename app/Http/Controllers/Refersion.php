<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Refersion extends Controller
{
    
    /**
     * __construct
     * Verifies webhook. If different app secrets apply to different webhooks, this could
     * be moved to each relevant function, with params added to verify_webhook() for more flexibility
     *
     * @return void
     */
    public function __construct()
    {
        $this->verify_webhook();
    }
    
    /**
     * create_trigger
     * Processes verified JSON-formatted webhook from Shopify
     *
     * @param  Request $request
     * @return void
     */
    public function create_trigger(Request $request){
        $webhook_data = file_get_contents('php://input');
        $product = json_decode($webhook_data);
        
        foreach($product->variants as $variant){
            $sku = $variant->sku;
            $affiliate_code = $this->process_affiliate($sku);
            //If the sku does not follow the prod-abc-rfsnadid:12345 naming convention, 
            //it will return a blank string (falsey value)
            if($affiliate_code){
                $this->send_request($affiliate_code, $sku);
            }
        }
    }
    
    /**
     * send_request
     * Requests new trigger creation on Refersion API
     * Requests with invalid affiliate codes or duplicate SKUs are gracefully declined 
     *
     * @param  string $affiliate_code
     * @param  string $sku
     * @return void
     */
    private function send_request($affiliate_code, $sku){        
        $response = Http::post('https://www.refersion.com/api/new_affiliate_trigger', [
            'refersion_public_key' => env('refersion_public_key'),
            'refersion_secret_key' => env('refersion_secret_key'),
            'affiliate_code' => $affiliate_code,
            'type' => 'SKU',
            'trigger' => $sku
        ]);
        //Optional: any kind of permanent or temporary logging    
        Log::debug(print_r($response, true));
    }
    
    /**
     * process_affiliate
     * Parses sku strings that follow specific naming convention and extracts affiliate code
     *
     * @param  string $sku
     * @return void
     */
    private function process_affiliate($sku){
        //If format is prod-abc-rfsnadid:12345 then parse 12345 as as affiliate id
        if(strpos($sku, 'rfsnadid:') !== false){
            $sku_array = explode(":", $sku);
            //By using the last element of the array, we allow for 
            //the possibility that colon may be used earlier in sku
            $affiliate_code = end($sku_array);
            return $affiliate_code;
        } else {
            return '';
        }
    }
    
    /**
     * verify_webhook
     * 
     * If different shopify_app_secret exist for different webhooks, we could make this more flexible
     * by adding one or more params to allow verification of different webhooks in one Controller. 
     * 
     * This could also be made more flexible to allow requests from localhost during dev,
     * or other sources during testing, etc.
     *
     * @return void
     */
    private function verify_webhook() {
        if (isset($_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'])) {
            $hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'];
            $data = file_get_contents('php://input');

            $calculated_hmac = base64_encode(hash_hmac('sha256', $data, env('shopify_app_secret'), true));
            $verified = ($hmac_header == $calculated_hmac);

            if (!$verified) {
                //My personal preference is to deny the existence of an endpoint to unverified requests
                abort(404);
            }
        } else {            
            abort(404);
        }
    }

}
