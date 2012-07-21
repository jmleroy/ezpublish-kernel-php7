<?php
/**
 * File containing the ViewController class.
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\MVC\Controller\Content;

use eZ\Publish\MVC\Controller,
    eZ\Publish\API\Repository\Repository,
    eZ\Publish\MVC\View\Manager as ViewManager,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class ViewController extends Controller
{
    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    private $repository;

    /**
     * @var \eZ\Publish\MVC\View\Manager
     */
    private $viewManager;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $request;

    public function __construct( Repository $repository, ViewManager $viewManager, Request $request )
    {
        $this->repository = $repository;
        $this->viewManager = $viewManager;
        $this->request = $request;
    }

    /**
     * Main action for viewing content through a location in the repository.
     * Response will be cached with HttpCache validation model (Etag)
     *
     * @param int $locationId
     * @param string $viewMode
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewLocationAction( $locationId, $viewMode )
    {
        $response = new Response();
        $response->setPublic();
        // TODO: Use a dedicated etag generator, generating a hash instead of plain text
        $response->setEtag( "ezpublish-location-$locationId-$viewMode" );
        if ( $response->isNotModified( $this->request ) )
        {
            return $response;
        }

        $location = $this->repository->getLocationService()->loadLocation( $locationId );

        // TODO: Use the view manager to generate the response content
        $generationDate = new \DateTime;
        $response->setContent(
            "Location #$locationId ($viewMode view mode).
            Path string is {$location->pathString}.
            Content name is {$location->getContentInfo()->name}
            Response generated at {$generationDate->format( 'Y-m-d H:i:s' )}"
        );

        return $response;
    }
}