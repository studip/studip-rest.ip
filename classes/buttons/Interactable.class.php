<?php
/*
 * Copyright (c) 2011 mlunzena@uos.de, aklassen@uos.de
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

namespace Studip;

/**
 * Represents an abstract interactable element.
 */
abstract class Interactable
{

    public $label, $attributes;

    /**
     * Constructs a new element to interact e.g. button or link
     *
     * @param string $label      the label of the button
     * @param array  $attributes the attributes of the button element
     */
    function __construct($label, $attributes)
    {
        $this->label      = $label;
        $this->attributes = $attributes;
    }

    /**
     * Magic method (triggered when invoking inaccessible methods in a static
     * context) used to dynamically create an interactable element with an
     * additional CSSclass. This works for every static method call matching:
     * /^create(.+)/ The matched group is used as CSS class for the
     * interactable element.
     *
     * @code
     * echo Button::createSubmit();
     *
     * # => <button ... class="submit">...
     * @endcode
     *
     * @param string $name  name of the method being called
     * @param array  $args  enumerated array containing the parameters
     *                      passed to the $name'ed method
     *
     * @return Interactable returns a Button, if $name =~ /^create/
     * @throws              throws a BadMethodCallException if $name does not
     *                      match
     */
    public static function __callStatic($name, $args)
    {
        # only trigger, if $name =~ /^create/ and at least using $label
        if (0 === strncasecmp($name, 'create', 6)) {

            # instantiate button from arguments
            $interactable = call_user_func_array(array(get_called_class(), 'create'), $args);
            # but customize with class from $name:
            $class = self::hyphenate(substr($name, 6));

            # a.) set name unless set
            if (!is_string(@$args[1])) {
                $interactable->attributes['name'] =  $class;
            }

            # b.) set/append CSS class
            if (array_key_exists('class', $interactable->attributes)) {
                $interactable->attributes['class'] .= " $class";
            } else {
                $interactable->attributes['class'] =  $class;
            }

            return $interactable;
        }

        # otherwise bail out
        throw new \BadMethodCallException();
    }

    /**
     * Easy factory method to create an Interactable instance.
     * All parameters are optional.
     *
     * @code
     * // example using subclass Button
     *
     * echo Button::create();
     * # => <button type="submit" name="ok">ok</button>
     *
     * echo Button::create('Yes')
     * # => <button type="submit" name="yes">yes</button>
     *
     * echo Button::create('Yes', 'aName')
     * # => <button type="submit" name="aName">yes</button>
     *
     * echo Button::create('Yes', array('a' => 1, 'b' => 2))
     * # => <button type="submit" a="1" b="2" name="yes">yes</button>
     *
     * echo Button::create('Yes', 'aName', array('a' => 1, 'b' => 2)),
     * # => <button type="submit" a="1" b="2" name="aName">yes</button>
     * @endcode
     *
     * @param string $label      the label of the current element
     * @param string $trait      the specific trait of the current element
     * @param array  $attributes the attributes of the button element
     *
     * @return returns a Interactable element
     */
    static function create($label = NULL, $trait = NULL, $attributes = array())
    {
        $argc = func_num_args();

        // if label is empty, use default
        $label = $label ?: _('ok');

        // if there are two parameters, there are two cases:
        //   1.) label and trait OR
        //   2.) label and attributes
        //
        // in the latter case, use parameter $trait as attributes
        // and use the default for name
        if ($argc === 2 && is_array($trait)) {
            list($attributes, $trait) = array($trait, NULL);
        }

        $called = get_called_class();
        $interactable = new $called($label, $attributes);
        $interactable->initialize($label, $trait, $attributes);

        return $interactable;
    }

    /**
     * Initialize an interactable element.
     * The parameters to create are handed over to enable subclass
     * specific customization.
     *
     * @param string $label      the label of the current element
     * @param string $trait      the specific trait of the current element
     * @param array  $attributes the attributes of the button element
     */
    abstract protected function initialize($label, $trait, $attributes);

    /**
     * Convenience method used for autocompletion hints by your
     * editor.
     *
     * Without this method #__callStatic would do the same.
     *
     * @param string $label      the label of the current element
     * @param string $trait      the specific trait of the current element
     * @param array  $attributes the attributes of the button element
     */
    static function createAccept($label = NULL, $trait = NULL, $attributes = array())
    {
        $args = func_num_args() ? func_get_args() : array('übernehmen');
        return self::__callStatic(__FUNCTION__, $args);
    }

    /**
     * Convenience method used for autocompletion hints by your
     * editor.
     *
     * Without this method #__callStatic would do the same.
     *
     * @param string $label      the label of the current element
     * @param string $trait      the specific trait of the current element
     * @param array  $attributes the attributes of the button element
     */
    static function createCancel($label = NULL, $trait = NULL, $attributes = array())
    {
        $args = func_num_args() ? func_get_args() : array('abbrechen');
        return self::__callStatic(__FUNCTION__, $args);
    }

    /**
     * Hyphenates the passed word.
     *
     * @param string $word  word to be hyphenated
     *
     * @return string   hyphenated word
     */
    private static function hyphenate($word)
    {
        return strtolower(preg_replace('/(?<=\w)([A-Z])/', '-\\1', $word));
    }
}
