<?php

namespace App\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * @package MultiDump
 * @author Marcin Szczyglinski <szczyglis@protonmail.com>
 * @link https://github.com/szczyglis-dev/symfony-multi-dump
 * @license MIT
 * @version 1.1 | 2020
 */
class MultiDump implements EventSubscriberInterface
{
    const ALLOW_FOR_ENVIRONMENTS = ['dev'];

    /**
     * @var KernelInterface
     */
    private $kernel;
    /**
     * @var Security
     */
    private $security;
    /**
     * @var RequestStack
     */
    private $requestStack;
    /**
     * @var SessionInterface
     */
    private $session;
    /**
     * @var string
     */
    private $prefix;
    /**
     * @var array
     */
    private static $vars = [];
    /**
     * @var array
     */
    private static $callbacks = [];
    /**
     * @var int
     */
    private $counter = 0;

    /**
     * MultiDump constructor.
     * @param KernelInterface $kernel
     * @param RequestStack $requestStack
     * @param Security $security
     * @param SessionInterface $session
     */
    public function __construct(KernelInterface $kernel, RequestStack $requestStack, Security $security, SessionInterface $session)
    {
        $this->kernel = $kernel;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->session = $session;
        $this->prefix = '___mdbg' . substr(md5(time()), 0, 3);        
    }

    /**
     * @return void
     */
    private function initDefaults()
    {
        // You can add or remove default items here:

        self::dump($this->security->getUser(), 'primary', 'user');
        self::dump($this->requestStack, 'primary', 'requestStack');
        self::dump($this->session->all(), 'primary', 'session');
        self::dump($this->requestStack->getMasterRequest()->request->all(), 'primary', 'POST');
        self::dump($this->requestStack->getMasterRequest()->cookies->all(), 'primary', 'cookies');
    }

    /**
     * @return array
     */
    public function getVars()
    {
        return self::$vars;
    }

    /**
     * @return string
     */
    private function generate()
    {
        $startCounter = $this->countVars();
        $this->initDefaults();   
        $afterCounter = $this->countVars() - $startCounter;
        $cloner = new VarCloner();
        $dumper = new HtmlDumper();
        $dumped = '';
        $callbacksOutputs = [];

        foreach (self::$vars as $section => $vars) {
            if (isset(self::$callbacks[$section]) && is_array(self::$callbacks[$section])) {
                foreach (self::$callbacks[$section] as $data) {
                    if (is_callable($data['callback'])) {
                        $callbacksOutputs[$section][] = $this->beginBlock('dumped_item');
                        if (!is_null($data['title'])) {
                            $callbacksOutputs[$section][] = $this->beginBlock('subheader') . $data['title'] . $this->endBlock();
                        }
                        $callbacksOutputs[$section][] = $data['callback']($this->kernel);
                        $callbacksOutputs[$section][] = $this->endBlock();
                    }
                }
            }
        }       

        $this->counter = $this->countVars() - $afterCounter;  

        foreach (self::$vars as $section => $vars) {
            $dumped .= $this->beginBlock('col');
            $dumped .= $this->beginBlock('header') . strtoupper($section) . ' ▼' . $this->endBlock();

            if (isset($callbacksOutputs[$section]) && is_array($callbacksOutputs[$section])) {
                $dumped .= implode('', $callbacksOutputs[$section]);
            }

            foreach ($vars as $k => $data) {
                $dumped .= $this->beginBlock('dumped_item');

                if (!is_null($data['title'])) {
                    $dumped .= $this->beginBlock('subheader') . $data['title'] . $this->endBlock();
                } else {
                    if (isset($data['file']) && isset($data['line'])) {
                        $dumped .= $this->beginBlock('subheader') . basename($data['file']) . ':' . $data['line'] . $this->endBlock();
                    }
                    if ((isset($data['class']) && !empty($data['class'])) || (isset($data['function']) && !empty($data['function']))) {
                        $dumped .= $this->beginText('trace');
                    }
                    if (isset($data['class']) && !empty($data['class'])) {
                        $dumped .= (new \ReflectionClass($data['class']))->getShortName() . '::';
                    }
                    if (isset($data['function']) && !empty($data['function'])) {
                        $dumped .= $this->beginText('trace') . $data['function'] . $this->endText();
                    }
                    if ((isset($data['class']) && !empty($data['class'])) || (isset($data['function']) && !empty($data['function']))) {
                        $dumped .= $this->endText();
                    }
                }

                $dumped .= $dumper->dump($cloner->cloneVar($data['var']), true);
                $dumped .= $this->endBlock();
            }

            $dumped .= $this->endBlock();
        }
        return $dumped;
    }

    /**
     * @return int
     */
    private function countVars()
    {
        $c = 0;
        foreach (self::$vars as $section) {
            $c += count($section);
        }
        return $c;
    }

    /**
     * @param null $class
     * @param null $id
     * @param string $extra
     * @return string
     */
    private function beginBlock($class = null, $id = null, $extra = '')
    {
        $classTag = '';
        $idTag = '';
        if (!is_null($class)) {
            $classTag = ' class="' . $this->prefix . $class . '"';
        }
        if (!is_null($id)) {
            $idTag = ' id="' . $this->prefix . $id . '"';
        }
        return '<div' . $idTag . $classTag . $extra . '>';
    }

    /**
     * @param null $class
     * @param null $id
     * @param string $extra
     * @return string
     */
    private function beginText($class = null, $id = null, $extra = '')
    {
        $classTag = '';
        $idTag = '';
        if (!is_null($class)) {
            $classTag = ' class="' . $this->prefix . $class . '"';
        }
        if (!is_null($id)) {
            $idTag = ' id="' . $this->prefix . $id . '"';
        }
        return '<span' . $idTag . $classTag . $extra . '>';
    }

    /**
     * @return string
     */
    private function endBlock()
    {
        return '</div>';
    }

    /**
     * @return string
     */
    private function endText()
    {
        return '</span>';
    }

    /**
     * @return string
     */
    private function flush()
    {        
        $dumped = $this->generate();
        $counter = '';
        if ($this->counter > 0) {
            $counter = ' ' . $this->counter . ' ';
        }

        $out = '';
        $out .= '<style>
    	#' . $this->prefix . 'container {    		
	    	position: fixed;
	    	background: #000;
	    	right: 0px;
	    	bottom: 20px;
	    	width: auto;
	    	height: 100%;
	    	z-index: 999998;
	    	display: none;
	    	opacity: 0.95;
	    	color: #fff;
	    	top:0;
	    	font-family: "Lucida Console", Monaco, monospace;
            font-size: 0.7rem;
	    	overflow-y: auto;
	    }
	    #' . $this->prefix . 'trigger {
	    	position: fixed;
	    	background: #222;
	    	right: 0px;
	    	bottom: 60px;
	    	width: auto;
	    	height: auto;
	    	text-align: center;
	    	padding: 10px;
	    	z-index: 999999;
	    	color: #fff;
	    	font-family: "Lucida Console", Monaco, monospace;
            font-size: 0.7rem;
            -webkit-border-top-left-radius: 5px;
            -webkit-border-bottom-left-radius: 5px;
            -moz-border-radius-topleft: 5px;
            -moz-border-radius-bottomleft: 5px;
            border-top-left-radius: 5px;
            border-bottom-left-radius: 5px;
	    }
	    #' . $this->prefix . 'container .' . $this->prefix . 'header {
	    	font-size:0.9rem;
	    	font-family: "Lucida Console", Monaco, monospace;
            margin-bottom: 8px;
	    }
	    #' . $this->prefix . 'container .' . $this->prefix . 'subheader {
	    	font-size:0.8rem;
	    	color: #8BC34A;
	    	font-family: "Lucida Console", Monaco, monospace;
            margin-bottom: 4px;
            margin-top: 12px;
	    }
	    #' . $this->prefix . 'container .' . $this->prefix . 'trace {
	    	font-size:0.7rem;
	    	font-family: "Lucida Console", Monaco, monospace;
	    }
	    #' . $this->prefix . 'container .' . $this->prefix . 'trace .' . $this->prefix . 'trace_func {
	    	font-weight: bold;
	    }
	    #' . $this->prefix . 'container .' . $this->prefix . 'dumped_item {
	    	border-bottom: 1px solid #253226;
	    }
	    #' . $this->prefix . 'trigger:hover {
	    	background: #383838;
	    	cursor: pointer;
	    }
        #' . $this->prefix . 'clr {
            clear:both;
        }
	    #' . $this->prefix . 'container .' . $this->prefix . 'col {
	    	padding:4px 10px;
	    	font-family: "Lucida Console", Monaco, monospace;
		}
		@media only screen and (min-width:600px) {
		  #' . $this->prefix . 'container .' . $this->prefix . 'col {
		    float: left;
		  }
		}
    	</style>';

        $out .= $this->beginBlock(null, 'container');
        $out .= $dumped;
        $out .= $this->beginBlock('clr').$this->endBlock();
        $out .= $this->endBlock();
        $out .= $this->beginBlock(null, 'trigger', ' title="open/close multi-dump window"');
        $out .= '<svg style="vertical-align:middle" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#AAA" d="M12 22.6c-5.8 0-10.5-4.7-10.5-10.5S6.2 1.5 12 1.5 22.5 6.2 22.5 12c0 5.9-4.7 10.6-10.5 10.6zm0-18.1c-4.2 0-7.5 3.4-7.5 7.5 0 4.2 3.4 7.5 7.5 7.5s7.5-3.4 7.5-7.5-3.3-7.5-7.5-7.5z"></path><path fill="#AAA" d="M12 9.1c-.8 0-1.5-.7-1.5-1.5v-6c0-.8.7-1.5 1.5-1.5s1.5.7 1.5 1.5v6c0 .8-.7 1.5-1.5 1.5zm1.5 13.3v-6c0-.8-.7-1.5-1.5-1.5s-1.5.7-1.5 1.5v6c0 .8.7 1.5 1.5 1.5s1.5-.7 1.5-1.5zM23.9 12c0-.8-.7-1.5-1.5-1.5h-6c-.8 0-1.5.7-1.5 1.5s.7 1.5 1.5 1.5h6c.8 0 1.5-.7 1.5-1.5zM9.1 12c0-.8-.7-1.5-1.5-1.5h-6c-.8 0-1.5.7-1.5 1.5s.7 1.5 1.5 1.5h6c.8 0 1.5-.7 1.5-1.5z"></path></svg>';
        $out .= $counter;
        $out .= ' [' . $this->kernel->getEnvironment() . '] multi-dump';
        $out .= $this->endBlock();
        $out .= '<script>
        document.onkeydown = function(event) {
        event = event || window.event;
            var esc = false;
            if ("key" in event) {
                esc = (event.key === "Escape" || event.key === "Esc");
            } else {
                esc = (event.keyCode === 27);
            }
            if (esc) {
                var container = document.getElementById("' . $this->prefix . 'container");
                if (container.style.display == "block") {
                    container.style.display = "none";
                }
            }
        };
    	document.getElementById("' . $this->prefix . 'trigger").addEventListener("click", function(){
    		var container = document.getElementById("' . $this->prefix . 'container");
    		if (container.style.display == "block") {
    			container.style.display = "none";
			} else {
				container.style.display = "block";
			}
		});
    	</script>';
        return $out;
    }

    /**
     * @param $var
     * @param string $section
     * @param null $customTitle
     */
    public static function dump($var, $section = 'secondary', $customTitle = null)
    {
        if (!isset(self::$vars['primary'])) {
            self::$vars['primary'] = [];
        }

        $data = [];
        $data['var'] = $var;
        $data['title'] = $customTitle;
        $data['file'] = null;
        $data['line'] = null;
        $data['class'] = null;
        $data['function'] = null;
        $i = 0;
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
        if (isset($trace[1]['function']) && $trace[1]['function'] == 'mdump') {
            $i++;
        }
        if (isset($trace[$i])) {
            $data['file'] = $trace[$i]['file'];
            $data['line'] = $trace[$i]['line'];
        }
        $i++;
        if (isset($trace[$i])) {
            if (isset($trace[$i]['class'])) $data['class'] = $trace[$i]['class'];
            $data['function'] = $trace[$i]['function'];
        }

        self::$vars[$section][] = $data;
    }

    /**
     * @param string $section
     * @param $callback
     * @param null $title
     */
    public static function extend($section = 'primary', $callback, $title = null)
    {
        self::$callbacks[$section][] = [
            'callback' => $callback,
            'title' => $title
        ];
    }

    /**
     * @param $content
     */
    public function append(&$content)
    {
        if (strpos($content, '</body>') !== false) {
            $content = str_replace('</body>', $this->flush() . '</body>', $content);
        } else {
            $content .= $this->flush();
        }
    }

    /**
     * @param ResponseEvent $event
     */
    public function handleEvent(ResponseEvent $event)
    {
        if (!in_array($this->kernel->getEnvironment(), self::ALLOW_FOR_ENVIRONMENTS)) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') == '_wdt' || $request->attributes->get('_route') == '_profiler' || $this->requestStack->getParentRequest() !== null) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        $this->append($content);
        $response->setContent($content);
        $event->setResponse($response);
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $this->handleEvent($event);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}

require_once(__DIR__ . '/../Functions/mdump.php');