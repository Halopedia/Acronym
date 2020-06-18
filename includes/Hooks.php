<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file Hooks.php
 * @author Daniel Beard
 */

namespace MediaWiki\Extension\Acronym;

/**
 * A class which contains a number of static hook functions, which are used to
 * register the Acronym parser functions
 */
class Hooks {

  /**
   * Hook called when parser is first initialised. Initialises the acronym
   * cache object that will fetch, decode and cache the acronyms from the given
   * JSON file, and store them for future use.
   * 
   * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
   * @param \Parser $parser the parser object that is being initialised
   */
  public static function onParserFirstCallInit( \Parser $parser ) {
    global $egAcronymDisabled;

    // Do nothing if the extension has been deliberately disabled
    if ( $egAcronymDisabled ) {
      return;
    }

    // Initialise the acronym cache
    AcronymCache::initCache( $parser );

    // Register the parser functions
    self::registerParserFuncObj( $parser, 'acronym' );
    self::registerParserFuncObj( $parser, 'acronymexists' );
  }

  /**
   * Registers a parser function with the parser, using the SFH object function
   * parameters (i.e. with the SFH_OBJECT_ARGS flag enabled).
   * 
   * @param \Parser $parser the parser to register the parser function with
   * @param string $name the name of the parser function to register
   */
  private static function registerParserFuncObj(
    \Parser $parser, string $name
  ) : void {
    global $egAcronymDisabledFunctions;

    // Do nothing if the parser function is disabled
    if (
      !empty( $egAcronymDisabledFunctions )
      && in_array( $name, $egAcronymDisabledFunctions )
    ) {
      return;
    }

    // Otherwise, register the function
    $parser->setFunctionHook(
      $name,
      [ AcronymCache::class, "parserFuncObj_$name" ],
      \Parser::SFH_OBJECT_ARGS
    );
  }

  /**
   * Hook called when parser is having its state cleared, so it can parse
   * another page. Resets the acronym cache, so that new pages will always
   * be generated using the latest version of the acronyms.
   * 
   * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserClearState
   * @param \Parser $parser the parser object that is having its state cleared
   */
  public static function onParserClearState( \Parser $parser ) {
    AcronymCache::resetCache( $parser );
  }
}

?>