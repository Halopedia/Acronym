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
 * @file AcronymCache.php
 * @author Daniel Beard
 */

namespace MediaWiki\Extension\Acronym;

/**
 * A class wrapping a lazy-loaded cache of the acronyms and their properties, 
 * as well as functions for resetting that cache and handling related parser
 * function calls.
 */
class AcronymCache {

  /**
   * The default name of the page in the MediaWiki namespace which the acronym
   * data will be pulled from, if not otherwise specified. Will be
   * automatically suffixed with ".json".
   */
  private const DEFAULT_ACRONYM_SRC = "Acronyms";

  /**
   * The default category to search for an acronym in, if no category is
   * specified in the parser function and it is not overwritten by a config
   * variable.
   */
  private const DEFAULT_CATEGORY = 'all';

  /**
   * Stores whether or not the acronym data has been loaded and parsed. It is
   * initialised to false, but set to true when the data is updated. The data
   * will never be loaded again for this object once it is set to true - the
   * previously-cached data will be used instead.
   */
  private $initialised = false;

  /**
   * An array mapping acronym category names to individual associative arrays,
   * which in turn map valid acronyms to their normalised form.
   */
  private $acronyms = [];

  /**
   * An array mapping normalised acronyms to an array of properties.
   */
  private $properties = [];

  /**
   * Determines whether the given acronym exists in the given category, and
   * returns true if so and false otherwise.
   * 
   * @param string $acronym the acronym to check
   * @param ?string $category the category to check within
   * 
   * @return boolean true if the acronym exists, false otherwise
   */
  public function exists(
    string $acronym, string $category = null
  ) : bool {
    // Set the category to the default category if not specified
    if ( empty( $category ) ) {
      $category = empty( $egAcronymDefaultCategory )
        ? self::DEFAULT_CATEGORY
        : $egAcronymDefaultCategory;
    }
    // Then check for the existence of the acronym
    return !empty( $this->acronyms[ $category ] )
      && !empty( $this->acronyms[ $category ][ $acronym ]);
  }

  /**
   * Converts the given acronym into its normalised form within the given
   * category. Returns null if no such acronym exists.
   * 
   * @param string $acronym the acronym to convert
   * @param ?string $category the category to convert within
   * 
   * @return ?string the normalised form of the acronym, or null
   */
  public function normalise(
    string $acronym, string $category = null
  ) : ?string {
    global $egAcronymDefaultCategory;
    // Set the category to the default category if not specified
    if ( empty( $category ) ) {
      $category = empty( $egAcronymDefaultCategory )
        ? self::DEFAULT_CATEGORY
        : $egAcronymDefaultCategory;
    }
    // Then return the normalised form if it exists, null otherwise
    return $this->exists( $acronym, $category )
      ? $this->acronyms[ $category ][ $acronym ]
      : null;
  }

  /**
   * Checks if a given acronym has a property with the given name.
   * 
   * @param string $acronym the acronym to check
   * @param string $property the property to check
   * 
   * @return boolean true is the acronym has the property, false otherwise
   */
  public function hasProperty( string $acronym, string $property ) : bool {
    return !empty( $this->properties[ $acronym ] )
    && !empty( $this->properties[ $acronym ][ $property ]);
  }

  /**
   * Returns a property value associated with a given acronym, or null if there
   * is no such acronym or property.
   * 
   * @param string $acronym the acronym to get the property of
   * @param string $property the name of the property to retrieve
   * 
   * @return ?string the property value, or null if there's no such property
   */
  public function getProperty( string $acronym, string $property ) : ?string {
    return $this->hasProperty( $acronym, $property )
      ? $this->properties[ $acronym ][ $property ]
      : null;
  }

  /**
   * Updates the cache of the acronym names and properties, if necessary
   */
  public function update() : void {
    global $egAcronymSource;

    // Do nothing if the cache is already populated
    if ( $this->initialised ) {
      return;
    }

    // Set the initialised flag so that the data is not regenerated in future
    $this->initialised = true;

    // Blank the acronym arrays, ready for population
    $this->acronyms = [];
    $this->properties = [];

    // Retrieve and parse the JSON from the appropriate page
    $src = empty( $egAcronymSource )
      ? self::DEFAULT_ACRONYM_SRC
      : $egAcronymSource;
    $json = wfMessage( "$src.json" )->plain();
    $parsed = json_decode( $json, true );

    // If the parsed JSON is empty, do nothing - there are no acronyms
    if ( empty( $parsed ) ) {
      return;
    }

    if ( !empty( $parsed[ 'acronyms' ] ) ) {
      // If the parsed JSON includes an acronym object, add its contents to the
      // acronym array, sanitising all of the values
      foreach ( $parsed[ 'acronyms' ] as $category => $content ) {
        $sanCat = self::sanitise( $category );
        if ( empty( $sanCat ) ) {
          continue;
        }
        if ( empty( $this->acronyms[ $sanCat ] ) ) {
          $this->acronyms[ $sanCat ] = [];
        }
        foreach( $content as $key => $val ) {
          $sanKey = self::sanitise( $key );
          $sanVal = self::sanitise( $val );
          if ( empty( $sanKey ) || empty( $sanVal ) ) {
            continue;
          }
          $this->acronyms[ $sanCat ][ $sanKey ] = $sanVal;
        }
      }
    }

    if ( !empty( $parsed[ 'properties' ] ) ) {
      // If the parsed JSON includes a properties object, add its contents to
      // the properties array, sanitising all of the values except for the
      // actual properties value
      foreach ( $parsed[ 'properties' ] as $key => $val ) {
        $sanKey = self::sanitise( $key );
        if ( empty( $sanKey ) ) {
          continue;
        }
        if ( empty( $this->properties[ $sanKey ] ) ) {
          $this->properties[ $sanKey ] = [];
        }
        foreach( $val as $propKey => $propVal ) {
          $sanPropKey = self::sanitise( $propKey );
          $this->properties[ $sanKey ][ $sanPropKey ] = $propVal;
        }
      }
    }
  }

  /**
   * Initialises the acronym cache for a given Parser object.
   * 
   * @param \Parser $parser the Parser object to initialise the cache for
   */
  public static function initCache( \Parser $parser ) : void {
    $parser->extAcronymCache = new AcronymCache();
  }

  /**
   * Resets the acronym cache for a given Parser object.
   * 
   * @param \Parser $parser the Parser object to reset the cache for
   */
  public static function resetCache( \Parser $parser ) : void {
    $parser->extAcronymCache = new AcronymCache();
  }

  /**
   * Retrieves the acronym cache for a given parser object.
   * 
   * @param \Parser $parser the Parser object to retrieve the cache from
   * 
   * @return AcronymCache the acronym cache associated with the given parser
   */
  private static function getCache( \Parser $parser ) : AcronymCache {
    return $parser->extAcronymCache;
  }

  /**
   * Sanitises a given string for use as an acronym key in internal arrays, by
   * trimming leading and trailing whitespace, and converting it to lower case.
   * 
   * @param string $string the string to sanitise
   * 
   * @return string the sanitised string
   */
  private static function sanitise( string $string ) : string {
    return mb_strtolower( trim( $string ) );
  }

  /**
   * The function that is used to process calls to the acronym parser
   * function.
   * 
   * @param \Parser $parser the parser currently parsing the relevant page
   * @param \PPFrame $frame the parser frame currently being processed
   * @param array $args the arguments passed 
   * 
   * @return string the wiki markup to be parsed and displayed on the page
   */
  public static function parserFuncObj_acronym(
    \Parser $parser, \PPFrame $frame, array $args
  ) {
    global $egAcronymDefaultCategory;

    // Parse the category and acronym arguments
    if ( empty( $args[1] ) ) {
      $category = '';
      $acronym = $frame->expand( $args[0] );
    } else {
      $category = $frame->expand( $args[0] );
      $acronym = $frame->expand( $args[1] );
    }

    // Parse the property argument
    $prop = empty( $args[2] ) ? '' : $frame->expand( $args[2] );

    // Sanitise all arguments
    $acronym = self::sanitise( $acronym );
    $category = self::sanitise( $category );
    $prop = self::sanitise( $prop );

    // Get the acronym store and update the acronyms
    $cache = self::getCache( $parser );
    $cache->update();
    
    // Normalise the acronym
    $acronym = $cache->normalise( $acronym, $category );

    // If no valid acronym was returned (i.e. result was null or empty), then
    // acronym does not exist, so return the negative outcome, which is either
    // the fourth argument specified by the user, or an empty string
    if ( empty( $acronym ) ) {
      return empty( $args[3] ) ? '' : $frame->expand( $args[3] );
    }

    if ( empty( $prop ) ) {
      // If no property was given, just return the sanitised and normalised
      // acronym.
      return $acronym;
    } else {
      // Otherwise, get the property...
      $result = $cache->getProperty( $acronym, $prop );

      if ( isset( $result ) ) {
        // If it existed, return it.
        return $result;
      } else {
        // Otherwise, return negative.
        return empty( $args[3] ) ? '' : $frame->expand( $args[3] );
      } 
    }
  }

  /**
   * The function that is used to process calls to the acronymexists parser
   * function.
   * 
   * @param \Parser $parser the parser currently parsing the relevant page
   * @param \PPFrame $frame the parser frame currently being processed
   * @param array $args the arguments passed 
   * 
   * @return string the wiki markup to be parsed and displayed on the page
   */
  public static function parserFuncObj_acronymexists( 
    \Parser $parser, \PPFrame $frame, array $args
  ) {
    global $egAcronymDefaultCategory;

    // Parse the category and acronym arguments
    if ( empty( $args[1] ) ) {
      $category = '';
      $acronym = $frame->expand( $args[0] );
    } else {
      $category = $frame->expand( $args[0] );
      $acronym = $frame->expand( $args[1] );
    }
    // Sanitise all arguments
    $acronym = self::sanitise( $acronym );
    $category = self::sanitise( $category );

    // Get the acronym store and update the acronyms
    $cache = self::getCache( $parser );
    $cache->update();

    if ( $cache->exists( $acronym, $category ) ) {
      // If there is a corresponding entry in the acronyms array to the
      // acronym, return positive (either output 'yes', or expand and output
      // the user-specified positive outcome).
      return empty( $args[2] ) ? 'yes' : $frame->expand( $args[2] );
    } else {
      // Otherwise, return negative (either a blank output, or the user-
      // specified negative outcome)
      return empty( $args[3] ) ? '' : $frame->expand( $args[3] );
    }
  }
}

?>