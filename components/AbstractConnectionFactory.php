<?php declare(strict_types=1);

namespace mikemadisonweb\rabbitmq\components;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AbstractConnectionFactory
{
    /** @var \ReflectionClass */
    private $_class;

    /** @var array */
    private $_parameters;

    /**
     * Constructor
     *
     * @param string $class      FQCN of AMQPConnection class to instantiate.
     * @param array  $parameters Map containing parameters resolved by Extension.
     */
    public function __construct($class, array $parameters)
    {
        $this->_class = $class;
        $this->_parameters = $this->parseUrl($parameters);
    }

    /**
     * @return mixed
     */
    public function createConnection() : AbstractConnection
    {
        // Para AMQPStreamConnection, el ssl_context va como parámetro $context (12º parámetro)
        if ($this->_class === AMQPStreamConnection::class) {
            $context = null;
            if ($this->_parameters['ssl_context'] !== null && is_array($this->_parameters['ssl_context'])) {
                // Crear contexto de stream a partir del array ssl_context
                $context = stream_context_create([
                    'ssl' => $this->_parameters['ssl_context']
                ]);
            }
            
            return new $this->_class(
                $this->_parameters['host'],
                $this->_parameters['port'],
                $this->_parameters['user'],
                $this->_parameters['password'],
                $this->_parameters['vhost'],
                false,      // insist
                'AMQPLAIN', // login_method
                null,       // login_response
                'en_US',    // locale
                $this->_parameters['connection_timeout'],
                $this->_parameters['read_write_timeout'],
                $context, // context (12º parámetro)
                $this->_parameters['keepalive'],
                $this->_parameters['heartbeat'],
                $this->_parameters['channel_rpc_timeout']
            );
        }
        
        // Para otras conexiones (como AMQPSSLConnection), mantener el comportamiento original
        if ($this->_parameters['ssl_context'] !== null) {
            return new $this->_class(
                $this->_parameters['host'],
                $this->_parameters['port'],
                $this->_parameters['user'],
                $this->_parameters['password'],
                $this->_parameters['vhost'],
                $this->_parameters['ssl_context'],
                [
                    'connection_timeout' => $this->_parameters['connection_timeout'],
                    'read_write_timeout' => $this->_parameters['read_write_timeout'],
                    'keepalive' => $this->_parameters['keepalive'],
                    'heartbeat' => $this->_parameters['heartbeat'],
                    'channel_rpc_timeout' => $this->_parameters['channel_rpc_timeout'],
                ]
            );
        }
        return new $this->_class(
            $this->_parameters['host'],
            $this->_parameters['port'],
            $this->_parameters['user'],
            $this->_parameters['password'],
            $this->_parameters['vhost'],
            false,      // insist
            'AMQPLAIN', // login_method
            null,       // login_response
            'en_EN',    // locale
            $this->_parameters['connection_timeout'],
            $this->_parameters['read_write_timeout'],
            $this->_parameters['ssl_context'],
            $this->_parameters['keepalive'],
            $this->_parameters['heartbeat'],
            $this->_parameters['channel_rpc_timeout']
        );
    }

    /**
     * Parse connection defined by url, e.g. 'amqp://guest:password@localhost:5672/vhost?lazy=1&connection_timeout=6'
     * @param $parameters
     * @return array
     */
    private function parseUrl($parameters)
    {
        if (!$parameters['url']) {
            return $parameters;
        }
        $url = parse_url($parameters['url']);
        if ($url === false || !isset($url['scheme']) || $url['scheme'] !== 'amqp') {
            throw new \InvalidArgumentException('Malformed parameter "url".');
        }
        if (isset($url['host'])) {
            $parameters['host'] = urldecode($url['host']);
        }
        if (isset($url['port'])) {
            $parameters['port'] = (int)$url['port'];
        }
        if (isset($url['user'])) {
            $parameters['user'] = urldecode($url['user']);
        }
        if (isset($url['pass'])) {
            $parameters['password'] = urldecode($url['pass']);
        }
        if (isset($url['path'])) {
            $parameters['vhost'] = urldecode(ltrim($url['path'], '/'));
        }
        if (isset($url['query'])) {
            $query = [];
            parse_str($url['query'], $query);
            $parameters = array_merge($parameters, $query);
        }
        unset($parameters['url']);

        return $parameters;
    }
}
