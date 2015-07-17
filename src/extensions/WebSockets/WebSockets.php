<?php
class WebSockets extends CApplicationComponent
{
    public $server;
    public $zmqHost;
    public $zmqPort;
    public $maxRetries = 50;
    public $retryDelay = 50;

    public function registerScripts () {
        $assets=dirname(__FILE__).'/assets';
        $baseUrl=Yii::app()->assetManager->publish($assets);
        if(is_dir($assets)){
            Yii::app()->clientScript->registerScriptFile($baseUrl.'/autobahn.min.js',CClientScript::POS_HEAD);
            Yii::app()->clientScript->registerScript('websocketsServer', <<<here
var webSocketSession = null;
webSocketSession = {
    subscribes: [],
    session: null,
    subscribe: function(channel, callback){
        if (typeof webSocketSession.subscribes[channel] === "undefined") {
            webSocketSession.subscribes[channel] = [];
        }
        webSocketSession.subscribes[channel].push(callback);
        if (webSocketSession.session) {
            webSocketSession.session.subscribe(channel, callback);
        }
    },
    resubscribe: function(){
        for (channel in webSocketSession.subscribes) {
            for (j in webSocketSession.subscribes[channel]) {
                if (typeof webSocketSession.subscribes[channel][j] == "function") {
                    webSocketSession.session.subscribe(channel, webSocketSession.subscribes[channel][j]);
                }
            }
        }
    }
};

ab.connect(
   'ws://'+document.location.host+'$this->server',
   function (session) {
        webSocketSession.session = session;
        webSocketSession.resubscribe();
   },
   function (code, reason, detail) {

   },
   {
       'maxRetries': $this->maxRetries,
       'retryDelay': $this->retryDelay
   }
);
webSocketSession.subscribe("123", function (topic, event) {
    console.log("Event 1 received!" + topic + '" : ', event);
});
here
                , CClientScript::POS_BEGIN);
        } else {
            throw new Exception('Realplexor - Error: Couldn\'t find assets to publish.');
        }
    }

    public function send($channel, $data)
    {
        \CoreLight::getInstance()->getWebSocketsManager()->send($channel, $data);
    }
}