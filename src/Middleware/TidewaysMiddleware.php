<?php

namespace ExtraSwoft\Tideways\Middleware;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swoft\Bean\Annotation\Bean;
use Swoft\Bean\Annotation\Value;
use Swoft\Exception\Exception;
use Swoft\Http\Message\Middleware\MiddlewareInterface;
use Swoole\Http\Request;


/**
 * @Bean()
 * @uses      TidewaysMiddleware
 * @version   2018年05月29日
 * @author    masixun <masixun71@foxmail.com>
 * @copyright Copyright 2010-2017 Swoft software
 * @license   PHP Version 7.x {@link http://www.php.net/license/3_0.txt}
 */
class TidewaysMiddleware implements MiddlewareInterface
{


    /**
     * @Value("${config.tideways.root}")
     * @var string
     */
    public $root = '';

    /**
     * @Value("${config.tideways.start}")
     * @var bool
     */
    public $start = true;

    /**
     * @Value("${config.tideways.host}")
     * @var string
     */
    public $host = 'mongodb://127.0.0.1:27017';

    /**
     * @Value("${config.tideways.db}")
     * @var string
     */
    public $db = 'xhprof';




    /**
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \InvalidArgumentException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        if ($this->start)
        {
            \tideways_enable(TIDEWAYS_FLAGS_CPU | TIDEWAYS_FLAGS_MEMORY | TIDEWAYS_FLAGS_NO_SPANS);
            $response = $handler->handle($request);
            $this->disable($request->getSwooleRequest());
        }
        else
        {
            $response = $handler->handle($request);
        }

        return $response;
    }


    private function disable(Request $request)
    {

        $dir = $this->root;
        require_once $dir . '/src/Xhgui/Config.php';
        \Xhgui_Config::load($dir . '/config/config.default.php');
        if (file_exists($dir . '/config/config.php')) {
            \Xhgui_Config::load($dir . '/config/config.php');
        }

        \Xhgui_Config::write('extension', 'tideways');
        \Xhgui_Config::write('db.host', $this->host);
        \Xhgui_Config::write('db.db', $this->db);

        if ((!extension_loaded('mongo') && !extension_loaded('mongodb')) && \Xhgui_Config::read('save.handler') === 'mongodb') {
            throw new \RuntimeException('xhgui - extension mongo not loaded');
            return;
        }

        if (!\Xhgui_Config::shouldRun()) {
            return;
        }

        if (!isset($request->server['REQUEST_TIME_FLOAT'])) {
            $request->server['REQUEST_TIME_FLOAT'] = microtime(true);
        }

        $data['profile'] = \tideways_disable();
        $sqlData = tideways_get_spans();
        $data['sql'] = array();
        if(isset($sqlData[1])){
            foreach($sqlData as $val){
                if(isset($val['n'])&&$val['n'] === 'sql'&&isset($val['a'])&&isset($val['a']['sql'])){
                    $_time_tmp = (isset($val['b'][0])&&isset($val['e'][0]))?($val['e'][0]-$val['b'][0]):0;
                    if(!empty($val['a']['sql'])){
                        $data['sql'][] = [
                            'time' => $_time_tmp,
                            'sql' => $val['a']['sql']
                        ];
                    }
                }
            }
        }

        // ignore_user_abort(true) allows your PHP script to continue executing, even if the user has terminated their request.
        // Further Reading: http://blog.preinheimer.com/index.php?/archives/248-When-does-a-user-abort.html
        // flush() asks PHP to send any data remaining in the output buffers. This is normally done when the script completes, but
        // since we're delaying that a bit by dealing with the xhprof stuff, we'll do it now to avoid making the user wait.
        ignore_user_abort(true);
        flush();
        $server = array_change_key_case($request->server,CASE_UPPER);

        if (!defined('XHGUI_ROOT_DIR')) {
            require $dir . '/src/bootstrap.php';
        }

        $uri = array_key_exists('REQUEST_URI', $server)
            ? $server['REQUEST_URI']
            : null;
        if (empty($uri) && isset($server['argv'])) {
            $cmd = basename($server['argv'][0]);
            $uri = $cmd . ' ' . implode(' ', array_slice($server['argv'], 1));
        }

        $time = array_key_exists('REQUEST_TIME', $server)
            ? $server['REQUEST_TIME']
            : time();
        $requestTimeFloat = explode('.', $server['REQUEST_TIME_FLOAT']);
        if (!isset($requestTimeFloat[1])) {
            $requestTimeFloat[1] = 0;
        }

        if (\Xhgui_Config::read('save.handler') === 'file') {
            $requestTs = array('sec' => $time, 'usec' => 0);
            $requestTsMicro = array('sec' => $requestTimeFloat[0], 'usec' => $requestTimeFloat[1]);
        } else {
            $requestTs = new \MongoDate($time);
            $requestTsMicro = new \MongoDate($requestTimeFloat[0], $requestTimeFloat[1]);
        }

        $data['date'] = $requestTs->toDateTime()->format('Y-m-d H:i:s');

        $data['meta'] = array(
            'url' => $uri,
            'SERVER' => $server,
            'get' => $request->get,
            'env' => [],
            'simple_url' => \Xhgui_Util::simpleUrl($uri),
            'request_ts' => $requestTs,
            'request_ts_micro' => $requestTsMicro,
            'request_date' => date('Y-m-d', $time),
        );


        try {
            $config = \Xhgui_Config::all();
            $config += array('db.options' => array());
            $saver = \Xhgui_Saver::factory($config);
            $saver->save($data);
        } catch (Exception $e) {
            throw new \RuntimeException('xhgui - ' . $e->getMessage());
        }


    }

}