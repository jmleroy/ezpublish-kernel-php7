<?php
/**
 * File containing Group location class
 *
 * @copyright Copyright (C) 1999-2011 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace ezp\User;
use ezp\Base\Exception\PropertyNotFound,
    ezp\Base\ModelInterface,
    ezp\Content\Location as ContentLocation,
    ezp\User\Group,
    ezp\User\Location as UserLocationAbstract;

/**
 * This class represents a Group location item
 */
class GroupLocation extends UserLocationAbstract implements ModelInterface
{
    /**
     * @var \ezp\User\Group The Group assigned to this location
     */
    protected $group;

    /**
     * Creates and setups User object
     *
     * @access private Use {@link \ezp\User\Service::assignGroupLocation()} to create objects of this type
     * @param \ezp\Content\Location $location
     * @param \ezp\User\Group|null $user
     */
    public function __construct( ContentLocation $location, Group $group = null )
    {
        $this->group = $group;
        parent::__construct( $location );
    }

    /**
     * Get group assigned to this location
     *
     * @return \ezp\User\Group
     */
    public function getGroup()
    {
        if ( $this->group !== null )
            return $this->group;

        return $this->group = new Group( $this->location->content );
    }


    /**
     * Sets internal variables on object from array
     *
     * Key is property name and value is property value.
     *
     * @access private
     * @param array $state
     * @return Model
     * @throws \ezp\Base\Exception\PropertyNotFound If one of the properties in $state is not found
     */
    public function setState( array $state )
    {
        foreach ( $state as $name => $value )
        {
            if ( property_exists( $this, $name ) )
                $this->$name = $value;
            else
                throw new PropertyNotFound( $name, get_class( $this ) );
        }
        return $this;
    }

    /**
     * Gets internal variables on object as array
     *
     * Key is property name and value is property value.
     *
     * @access private
     * @param string|null $property Optional, lets you specify to only return one property by name
     * @return array|mixed Always returns array if $property is null, else value of property
     * @throws \ezp\Base\Exception\PropertyNotFound If $property is not found (when not null)
     */
    public function getState( $property = null )
    {
        $arr = array();
        foreach ( $this as $name => $value )
        {
            if ( $property === $name )
                return $value;
            else if ( $property === null )
                $arr[$name] = $value;
        }

        if ( $property !== null )
            throw new PropertyNotFound( $property, get_class( $this ) );

        return $arr;
    }
}
