<?php
/*
 * This file is part of contao-phpbbBridge
 * 
 * Copyright (c) 2015-2016 Daniel Schwiperich
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 */

namespace Ctsmedia\Phpbb\BridgeBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;


/**
 * Listener to pass the client IP through
 *
 * @package Ctsmedia\Phpbb\BridgeBundle\EventListener
 * @author Daniel Schwiperich <https://github.com/DanielSchwiperich>
 */
class BypassProxyListener
{
    /**
     * Sets the bridge proxy as trusted (the proxy is always the server itself since we allow no forein requests)
     *
     * @param GetResponseEvent $event The event object
     */
    public function onKernelRequest(GetResponseEvent $event)
    {

        $req = $event->getRequest();
        if ($req->headers->get('x-requested-with') == 'ContaoPhpbbBridge') {
            // Add the local ip to the trusted proxies ones so we don't get the Server IP as Client IP for incomming bridge requests
            $proxies = $req->getTrustedProxies();
            $proxies[] = $req->server->get('SERVER_ADDR');
            $req->setTrustedProxies($proxies, 34359738367);
        }
    }
}
