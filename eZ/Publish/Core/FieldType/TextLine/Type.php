<?php
/**
 * File containing the TextLine class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\FieldType\TextLine;
use eZ\Publish\Core\FieldType\FieldType,
    ez\Publish\Core\Repository\ValidatorService,
    eZ\Publish\Core\Base\Exceptions\InvalidArgumentType,
    eZ\Publish\API\Repository\Values\ContentType\FieldDefinition,
    eZ\Publish\API\Repository\Values\Content\Field,
    eZ\Publish\Core\Base\Exceptions\InvalidArgumentException,
    eZ\Publish\Core\FieldType\ValidationError;

/**
 * The TextLine field type.
 *
 * This field type represents a simple string.
 */
class Type extends FieldType
{
    protected $validatorConfigurationSchema = array(
        "StringLengthValidator" => array(
            "minStringLength" => array(
                "type" => "int",
                "default" => 0
            ),
            "maxStringLength" => array(
                "type" => "int",
                "default" => null
            )
        )
    );

    /**
     * Build a Value object of current FieldType
     *
     * Build a FiledType\Value object with the provided $text as value.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     *
     * @param string $text
     *
     * @return \eZ\Publish\Core\FieldType\TextLine\Value
     */
    public function buildValue( $text )
    {
        return new Value( $text );
    }

    /**
     * Return the field type identifier for this field type
     *
     * @return string
     */
    public function getFieldTypeIdentifier()
    {
        return "ezstring";
    }

    /**
     * Returns the fallback default value of field type when no such default
     * value is provided in the field definition in content types.
     *
     * @return \eZ\Publish\Core\FieldType\TextLine\Value
     */
    public function getDefaultDefaultValue()
    {
        return new Value( '' );
    }

    /**
     * Checks the type and structure of the $Value.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if the parameter is not of the supported value sub type
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if the value does not match the expected structure
     *
     * @param \eZ\Publish\Core\FieldType\TextLine\Value $inputValue
     *
     * @return \eZ\Publish\Core\FieldType\TextLine\Value
     */
    public function acceptValue( $inputValue )
    {
        if ( !$inputValue instanceof Value )
        {
            throw new InvalidArgumentType(
                '$inputValue',
                'eZ\\Publish\\Core\\FieldType\\TextLine\\Value',
            $inputValue
            );
        }

        if ( !is_string( $inputValue->text ) )
        {
            throw new InvalidArgumentType(
                '$inputValue->text',
                'string',
            $inputValue->text
            );
        }

        return $inputValue;
    }

    /**
     * Returns information for FieldValue->$sortKey relevant to the field type.
     *
     * @todo String normalization should occur here.
     * @return array
     */
    protected function getSortInfo( $value )
    {
        return array( 'sort_key_string' => $value->text );
    }

    /**
     * Converts an $hash to the Value defined by the field type
     *
     * @param mixed $hash
     *
     * @return \eZ\Publish\Core\FieldType\TextLine\Value $value
     */
    public function fromHash( $hash )
    {
        return new Value( $hash );
    }

    /**
     * Converts a $Value to a hash
     *
     * @param \eZ\Publish\Core\FieldType\TextLine\Value $value
     *
     * @return mixed
     */
    public function toHash( $value )
    {
        return $value->text;
    }

    /**
     * Returns whether the field type is searchable
     *
     * @return bool
     */
    public function isSearchable()
    {
        return true;
    }

    /**
     * Validates the validatorConfiguration of a FieldDefinitionCreateStruct or FieldDefinitionUpdateStruct
     *
     * @param mixed $validatorConfiguration
     *
     * @return \eZ\Publish\SPI\FieldType\ValidationError[]
     */
    public function validateValidatorConfiguration( $validatorConfiguration )
    {
        $validationErrors = array();

        foreach ( (array)$validatorConfiguration as $validatorIdentifier => $parameters )
        {
            if ( isset( $this->validatorConfigurationSchema[$validatorIdentifier] ) )
            {
                foreach ( $parameters as $name => $value )
                {
                    switch ( $name )
                    {
                        case "minStringLength":
                        case "maxStringLength":
                            if ( !is_integer( $value ) )
                            {
                                $validationErrors[] = new ValidationError(
                                    "Validator parameter '%parameter%' must be of integer type",
                                    null,
                                    array(
                                        "parameter" => $name
                                    )
                                );
                            }
                            break;
                        default:
                            $validationErrors[] = new ValidationError(
                                "Validator parameter '%parameter%' is unknown",
                                null,
                                array(
                                    "parameter" => $name
                                )
                            );
                    }
                }
            }
            else
            {
                $validationErrors[] = new ValidationError(
                    "Validator '%validator%' is unknown",
                    null,
                    array(
                        "validator" => $validatorIdentifier
                    )
                );
            }
        }

        return $validationErrors;
    }
}