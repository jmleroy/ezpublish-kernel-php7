<?php
/**
 * File containing the RequestEventListenerTest class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Bundle\EzPublishCoreBundle\Tests\EventListener;

use eZ\Bundle\EzPublishCoreBundle\EventListener\RequestEventListener;
use eZ\Publish\Core\MVC\Symfony\SiteAccess;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use eZ\Bundle\EzPublishCoreBundle\Kernel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContext;

class RequestEventListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $configResolver;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $router;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var RequestEventListener
     */
    private $requestEventListener;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var GetResponseEvent
     */
    private $event;

    /**
     * @var HttpKernelInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpKernel;

    protected function setUp()
    {
        parent::setUp();

        $this->configResolver = $this->getMock( 'eZ\Publish\Core\MVC\ConfigResolverInterface' );
        $this->router = $this->getMock( 'Symfony\Component\Routing\RouterInterface' );
        $this->logger = $this->getMock( 'Psr\\Log\\LoggerInterface' );

        $this->requestEventListener = new RequestEventListener( $this->configResolver, $this->router, 'foobar', $this->logger );

        $this->request = $this
            ->getMockBuilder( 'Symfony\\Component\\HttpFoundation\\Request' )
            ->setMethods( array( 'getSession', 'hasSession' ) )
            ->getMock();

        $this->httpKernel = $this->getMock( 'Symfony\\Component\\HttpKernel\\HttpKernelInterface' );
        $this->event = new GetResponseEvent(
            $this->httpKernel,
            $this->request,
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    public function testSubscribedEvents()
    {
        $this->assertSame(
            array(
                KernelEvents::REQUEST => array(
                    array( 'onKernelRequestSetup', 190 ),
                    array( 'onKernelRequestForward', 10 ),
                    array( 'onKernelRequestRedirect', 0 ),
                    array( 'onKernelRequestIndex', 40 ),
                )
            ),
            $this->requestEventListener->getSubscribedEvents()
        );
    }

    /**
     * @dataProvider indexPageProvider
     */
    public function testOnKernelRequestIndexOnIndexPage( $requestPath, $configuredIndexPath, $expectedIndexPath )
    {
        $this->configResolver
            ->expects( $this->once() )
            ->method( 'getParameter' )
            ->with( 'index_page' )
            ->will( $this->returnValue( $configuredIndexPath ) );
        $this->request->attributes->set( 'semanticPathinfo', $requestPath );
        $this->requestEventListener->onKernelRequestIndex( $this->event );
        $this->assertEquals( $expectedIndexPath, $this->request->attributes->get( 'semanticPathinfo' ) );
        $this->assertTrue( $this->request->attributes->get( 'needsForward' ) );
    }

    public function indexPageProvider()
    {
        return array(
            array( '/', '/foo', '/foo' ),
            array( '/', '/foo/', '/foo/' ),
            array( '/', '/foo/bar', '/foo/bar' ),
            array( '/', 'foo/bar', '/foo/bar' ),
            array( '', 'foo/bar', '/foo/bar' ),
            array( '', '/foo/bar', '/foo/bar' ),
            array( '', '/foo/bar/', '/foo/bar/' ),
        );
    }

    public function testOnKernelRequestIndexNotOnIndexPage()
    {
        $this->request->attributes->set( 'semanticPathinfo', '/anyContent' );
        $this->requestEventListener->onKernelRequestIndex( $this->event );
        $this->assertFalse( $this->request->attributes->has( 'needsForward' ) );
    }

    public function testOnKernelRequestForwardSubRequest()
    {
        $this->httpKernel
            ->expects( $this->never() )
            ->method( 'handle' );

        $event = new GetResponseEvent( $this->httpKernel, new Request, HttpKernelInterface::SUB_REQUEST );
        $this->requestEventListener->onKernelRequestForward( $event );
    }

    public function testOnKernelRequestForward()
    {
        $queryParameters = array( 'some' => 'thing' );
        $cookieParameters = array( 'cookie' => 'value' );
        $request = Request::create( '/test_sa/foo/bar', 'GET', $queryParameters, $cookieParameters );
        $semanticPathinfo = '/foo/something';
        $request->attributes->set( 'semanticPathinfo', $semanticPathinfo );
        $request->attributes->set( 'needsForward', true );
        $request->attributes->set( 'someAttribute', 'someValue' );

        $expectedForwardRequest = Request::create( $semanticPathinfo, 'GET', $queryParameters, $cookieParameters );
        $expectedForwardRequest->attributes->set( 'semanticPathinfo', $semanticPathinfo );
        $expectedForwardRequest->attributes->set( 'someAttribute', 'someValue' );

        $response = new Response( 'Success!' );
        $this->httpKernel
            ->expects( $this->once() )
            ->method( 'handle' )
            ->with( $this->equalTo( $expectedForwardRequest ) )
            ->will( $this->returnValue( $response ) );

        $event = new GetResponseEvent( $this->httpKernel, $request, HttpKernelInterface::MASTER_REQUEST );
        $this->requestEventListener->onKernelRequestForward( $event );
        $this->assertSame( $response, $event->getResponse() );
        $this->assertTrue( $event->isPropagationStopped() );
    }

    public function testOnKernelRequestSetupSubrequest()
    {
        $this->router
            ->expects( $this->never() )
            ->method( 'getContext' );
        $this->router
            ->expects( $this->never() )
            ->method( 'setContext' );

        $event = new GetResponseEvent( $this->httpKernel, new Request, HttpKernelInterface::SUB_REQUEST );
        $this->requestEventListener->onKernelRequestSetup( $event );
        $this->assertFalse( $event->hasResponse() );
    }

    public function testOnKernelRequestSetupAlreadyHasSiteaccess()
    {
        $event = new GetResponseEvent( $this->httpKernel, new Request, HttpKernelInterface::MASTER_REQUEST );
        $this->requestEventListener->onKernelRequestSetup( $event );
        $this->assertFalse( $event->hasResponse() );
    }

    public function testOnKernelRequestSetupAlreadySetupUri()
    {
        $this->router
            ->expects( $this->once() )
            ->method( 'generate' )
            ->with( 'ezpublishSetup' )
            ->will( $this->returnValue( '/setup' ) );
        $this->router
            ->expects( $this->once() )
            ->method( 'getContext' )
            ->will( $this->returnValue( $this->getMock( 'Symfony\Component\Routing\RequestContext' ) ) );

        $requestEventListener = new RequestEventListener( $this->configResolver, $this->router, 'setup', $this->logger );
        $event = new GetResponseEvent( $this->httpKernel, Request::create( '/setup' ), HttpKernelInterface::MASTER_REQUEST );
        $requestEventListener->onKernelRequestSetup( $event );
        $this->assertFalse( $event->hasResponse() );
    }

    public function testOnKernelRequestSetup()
    {
        $this->router
            ->expects( $this->once() )
            ->method( 'generate' )
            ->with( 'ezpublishSetup' )
            ->will( $this->returnValue( '/setup' ) );
        $this->router
            ->expects( $this->once() )
            ->method( 'getContext' )
            ->will( $this->returnValue( $this->getMock( 'Symfony\Component\Routing\RequestContext' ) ) );

        $requestEventListener = new RequestEventListener( $this->configResolver, $this->router, 'setup', $this->logger );
        $event = new GetResponseEvent( $this->httpKernel, Request::create( '/foo/bar' ), HttpKernelInterface::MASTER_REQUEST );
        $requestEventListener->onKernelRequestSetup( $event );
        $this->assertTrue( $event->hasResponse() );
        /** @var RedirectResponse $response */
        $response = $event->getResponse();
        $this->assertInstanceOf( 'Symfony\Component\HttpFoundation\RedirectResponse', $response );
        $this->assertSame( '/setup', $response->getTargetUrl() );
    }

    public function testOnKernelRequestRedirectSubRequest()
    {
        $event = new GetResponseEvent( $this->httpKernel, new Request, HttpKernelInterface::SUB_REQUEST );
        $this->requestEventListener->onKernelRequestRedirect( $event );
        $this->assertFalse( $event->hasResponse() );
    }

    public function testOnKernelRequestRedirect()
    {
        $queryParameters = array( 'some' => 'thing' );
        $cookieParameters = array( 'cookie' => 'value' );
        $request = Request::create( '/test_sa/foo/bar', 'GET', $queryParameters, $cookieParameters );
        $semanticPathinfo = '/foo/something';
        $request->attributes->set( 'semanticPathinfo', $semanticPathinfo );
        $request->attributes->set( 'needsRedirect', true );
        $request->attributes->set( 'siteaccess', new SiteAccess() );

        $event = new GetResponseEvent( $this->httpKernel, $request, HttpKernelInterface::MASTER_REQUEST );
        $this->requestEventListener->onKernelRequestRedirect( $event );
        $this->assertTrue( $event->hasResponse() );
        /** @var RedirectResponse $response */
        $response = $event->getResponse();
        $this->assertInstanceOf( 'Symfony\Component\HttpFoundation\RedirectResponse', $response );
        $this->assertSame( "$semanticPathinfo?some=thing", $response->getTargetUrl() );
        $this->assertSame( 301, $response->getStatusCode() );
        $this->assertTrue( $event->isPropagationStopped() );
    }

    public function testOnKernelRequestRedirectPrependSiteaccess()
    {
        $queryParameters = array( 'some' => 'thing' );
        $cookieParameters = array( 'cookie' => 'value' );
        $siteaccessMatcher = $this->getMock( 'eZ\Publish\Core\MVC\Symfony\SiteAccess\URILexer' );
        $siteaccess = new SiteAccess( 'test', 'foo', $siteaccessMatcher );
        $semanticPathinfo = '/foo/something';

        $request = Request::create( '/test_sa/foo/bar', 'GET', $queryParameters, $cookieParameters );
        $request->attributes->set( 'semanticPathinfo', $semanticPathinfo );
        $request->attributes->set( 'needsRedirect', true );
        $request->attributes->set( 'siteaccess', $siteaccess );
        $request->attributes->set( 'prependSiteaccessOnRedirect', true );

        $expectedURI = "/test$semanticPathinfo";
        $siteaccessMatcher
            ->expects( $this->once() )
            ->method( 'analyseLink' )
            ->with( $semanticPathinfo )
            ->will( $this->returnValue( $expectedURI ) );

        $event = new GetResponseEvent( $this->httpKernel, $request, HttpKernelInterface::MASTER_REQUEST );
        $this->requestEventListener->onKernelRequestRedirect( $event );
        $this->assertTrue( $event->hasResponse() );
        /** @var RedirectResponse $response */
        $response = $event->getResponse();
        $this->assertInstanceOf( 'Symfony\Component\HttpFoundation\RedirectResponse', $response );
        $this->assertSame( "$expectedURI?some=thing", $response->getTargetUrl() );
        $this->assertSame( 301, $response->getStatusCode() );
        $this->assertTrue( $event->isPropagationStopped() );
    }
}
