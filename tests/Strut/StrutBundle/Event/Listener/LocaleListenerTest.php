<?php

namespace Tests\Strut\StrutBundle\Event\Listener;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Strut\StrutBundle\Event\Listener\LocaleListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleListenerTest extends WebTestCase
{
    private function getEvent(Request $request)
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')
            ->disableOriginalConstructor()
            ->getMock();

        return new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
    }

    public function testWithoutSession()
    {
        $request = Request::create('/');

        $listener = new LocaleListener('fr');
        $event = $this->getEvent($request);

        $listener->onKernelRequest($event);
        $this->assertEquals('en', $request->getLocale());
    }

    public function testWithPreviousSession()
    {
        $request = Request::create('/');
        // generate a previous session
        $request->cookies->set('MOCKSESSID', 'foo');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $listener = new LocaleListener('fr');
        $event = $this->getEvent($request);

        $listener->onKernelRequest($event);
        $this->assertEquals('fr', $request->getLocale());
    }

    public function testLocaleFromRequestAttribute()
    {
        $request = Request::create('/');
        // generate a previous session
        $request->cookies->set('MOCKSESSID', 'foo');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set('_locale', 'es');

        $listener = new LocaleListener('fr');
        $event = $this->getEvent($request);

        $listener->onKernelRequest($event);
        $this->assertEquals('en', $request->getLocale());
        $this->assertEquals('es', $request->getSession()->get('_locale'));
    }

    public function testSubscribedEvents()
    {
        $request = Request::create('/');
        // generate a previous session
        $request->cookies->set('MOCKSESSID', 'foo');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $listener = new LocaleListener('fr');
        $event = $this->getEvent($request);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($listener);

        $dispatcher->dispatch(
            KernelEvents::REQUEST,
            $event
        );

        $this->assertEquals('fr', $request->getLocale());
    }
}
