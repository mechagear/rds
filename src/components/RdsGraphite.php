<?php
use \GraphiteSystem\Graphite;

class RdsGraphite extends CComponent
{
    public $host;
    public $port;
    public $protocol;
    public $env;
    public $prefix;
    public $GUIUrl;

    /** @var Graphite */
    private $graphite;

    public function init() {
        $this->graphite = new Graphite([
            'host'      => $this->host,
            'port'      => $this->port,
            'protocol'  => $this->protocol,
            'env'       => $this->env,
            'prefix'    => $this->prefix,
        ]);
    }

    /** @return Graphite */
    public function getGraphite()
    {
        return $this->graphite;
    }
}