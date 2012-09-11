<?php
/**
 * File containing the legacy kernel Loader class.
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\MVC\Legacy\Kernel;

use eZ\Publish\Core\MVC\Legacy\Kernel as LegacyKernel,
    eZ\Publish\Core\MVC\Legacy\LegacyEvents,
    eZ\Publish\Core\MVC\Legacy\Event\PreBuildKernelWebHandlerEvent,
    eZ\Publish\Core\MVC\Symfony\SiteAccess,
    ezpKernelHandler,
    ezpKernelWeb,
    ezpKernelTreeMenu,
    eZURI,
    Symfony\Component\DependencyInjection\ContainerInterface,
    Symfony\Component\DependencyInjection\Exception\InactiveScopeException,
    Symfony\Component\HttpKernel\Log\LoggerInterface,
    Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Legacy kernel loader
 */
class Loader
{
    /**
     * @var string $legacyRootDir Absolute path to the legacy root directory (eZPublish 4 install dir)
     */
    protected $legacyRootDir;

    /**
     * @var string Absolute path to the new webroot directory (web/)
     */
    protected $webrootDir;

    /**
     * @var \Symfony\Component\HttpKernel\Log\LoggerInterface
     */
    protected $logger;

    public function __construct( $legacyRootDir, $webrootDir, LoggerInterface $logger = null )
    {
        $this->legacyRootDir = $legacyRootDir;
        $this->webrootDir = $webrootDir;
        $this->logger = $logger;
    }

    /**
     * Builds up the legacy kernel and encapsulates it inside a closure, allowing lazy loading.
     *
     * @param \ezpKernelHandler|\Closure A kernel handler instance or a closure returning a kernel handler instance
     * @return \Closure
     */
    public function buildLegacyKernel( $legacyKernelHandler )
    {
        $legacyRootDir = $this->legacyRootDir;
        $webrootDir = $this->webrootDir;
        return function () use ( $legacyKernelHandler, $legacyRootDir, $webrootDir )
        {
            static $legacyKernel;
            if ( !$legacyKernel instanceof LegacyKernel )
            {
                if ( $legacyKernelHandler instanceof \Closure )
                    $legacyKernelHandler = $legacyKernelHandler();
                $legacyKernel = new LegacyKernel( $legacyKernelHandler, $legacyRootDir, $webrootDir );
            }

            return $legacyKernel;
        };
    }

    /**
     * Builds up the legacy kernel web handler and encapsulates it inside a closure, allowing lazy loading.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param $webHandlerClass The legacy kernel handler class to use
     * @param array $defaultLegacyOptions Hash of options to pass to the legacy kernel handler
     *
     * @throws \InvalidArgumentException
     * @return \Closure|void
     */
    public function buildLegacyKernelHandlerWeb( ContainerInterface $container, $webHandlerClass, array $defaultLegacyOptions = array() )
    {
        $legacyRootDir = $this->legacyRootDir;
        $webrootDir = $this->webrootDir;
        try
        {
            // Getting the request through the container since this service is in the "request" scope and we are not in this scope yet.
            // Moreover, while clearing/warming up caches with app/console we might get an InactiveScopeException
            // since the "request" scope is only active via web.
            $request = $container->get( 'request' );
        }
        catch ( InactiveScopeException $e )
        {
            // Not in web mode. We have nothing to do here.
            if ( isset( $this->logger ) )
                $this->logger->info( 'Trying to get the request in non-web context (warming up caches?), aborting', array( __METHOD__ ) );

            return;
        }

        $eventDispatcher = $container->get( 'event_dispatcher' );
        $legacyParameters = new ParameterBag( $defaultLegacyOptions );

        return function () use ( $legacyRootDir, $webrootDir, $request, $eventDispatcher, $legacyParameters, $webHandlerClass )
        {
            static $webHandler;
            if ( !$webHandler instanceof ezpKernelHandler )
            {
                chdir( $legacyRootDir );

                $buildEvent = new PreBuildKernelWebHandlerEvent(
                    $legacyParameters, $request
                );
                $eventDispatcher->dispatch(
                    LegacyEvents::PRE_BUILD_LEGACY_KERNEL_WEB, $buildEvent
                );

                $interfaces = class_implements( $webHandlerClass );
                if ( !isset( $interfaces['ezpKernelHandler'] ) )
                    throw new \InvalidArgumentException( 'A legacy kernel handler must be an instance of ezpKernelHandler.' );

                $webHandler = new $webHandlerClass( $legacyParameters->all() );
                eZURI::instance()->setURIString(
                    $request->attributes->get(
                        'semanticPathinfo',
                        $request->getPathinfo()
                    )
                );
                chdir( $webrootDir );
            }

            return $webHandler;
        };
    }

    /**
     * @param array $settings
     *
     * @return CLIHandler
     */
    public function buildLegacyKernelHandlerCLI( array $settings = array() )
    {
        chdir( $this->legacyRootDir );
        $cliHandler = new CLIHandler( $settings );
        chdir( $this->webrootDir );

        return $cliHandler;
    }

    /**
     * Builds the legacy kernel handler for the tree menu in admin interface.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @return \Closure A closure returning an \ezpKernelTreeMenu instance.
     */
    public function buildLegacyKernelHandlerTreeMenu( ContainerInterface $container )
    {
        return $this->buildLegacyKernelHandlerWeb(
            $container,
            $container->getParameter( 'ezpublish_legacy.kernel_handler.treemenu.class' ),
            array(
                 'use-cache-headers'    => false,
                 'use-exceptions'       => true
            )
        );
    }
}