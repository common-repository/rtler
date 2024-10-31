<?php
/**
 * RTLer Class by Louy
 * @author Louy Alakkad <louy08@gmail.com>
 * @website http://louyblog.wordpress.com/
 */
class RTLer {
	
	/**
	 * padding, margin and everything that can have -right and -left;
	 */
	var $properties  = array( 'padding' => '0',
						'margin' => 'auto',
						'border' => 'none',
						// '-moz-border-radius' => '0'
						);
	
	
	var $have = array();
	
	/**
	 * parse one declaration of css, if it has something that can be RTLed then do our job.
	 * anyway, if not, return false.
	 */
	function parse_declaration( $declaration ) {
		$properties  = implode('|', array_keys($this->properties));
		// check if it has right or left word.
		if( preg_match( '/(right|left)/', $declaration ) ) {
			
			if( preg_match( '/^([\s\t]*)(right|left)\:/', $declaration ) ) {
				$is_right = preg_match( '/^([\s\t]*)right\:/', $declaration );
				if( $this->have[''] === ($is_right?'left':'right') ) {
					$this->have[''] = false;
				} else {
					$this->have[''] = $is_right?'right':'left';
				}
			}
			// same as the above code
			if( preg_match( '/^([\s\t]*)('.$properties.')-(right|left)\:/', $declaration, $matches ) ) {
				$property = $matches[2];
				$is_right = ($matches[3] == 'right');
				if( $this->have[$property] === ($is_right?'left':'right') ) {
					$this->have[$property] = false;
				} else {
					$this->have[$property] = $is_right?'right':'left';
				}
			}
			
			// flip right and left.
			$declaration = $this->right_to_left( $declaration );
			
		} elseif( preg_match( '/('.$properties.'):(([\s\t]*)(auto|-?[0-9\.]{1,6}(em|%|px)?)([\s\t]+)(auto|-?[0-9\.]{1,6}(em|%|px)?)([\s\t]+)(auto|-?[0-9\.]{1,6}(em|%|px)?)([\s\t]+)(auto|-?[0-9\.]{1,6}(em|%|px)?)([\s\t]*)(!important)?([\s\t]*);)/', $declaration, $matches ) ) {
			// If it's <code>padding: 1 2 3 4;</code> we'll flip the 2nd and the 4th values.
			
			// first, if they are equal, return false.
			if( $matches[7] == $matches[13] )
			
				$declaration = false;
				
			else
			
				// now flip
				$declaration = str_replace( $matches[2], $matches[3].$matches[4].$matches[6].$matches[13].$matches[9].$matches[10].$matches[12].$matches[7].$matches[15].$matches[16].';', $declaration );
				
		} else { // no RTL to do, return false
			$declaration = false;
		}
		
		// return the result.
		return $declaration;
	}
	
	/**
	 * explode block to declarations, call $this->parse_declaration on each,
	 * then add neccesary code to the end of block;
	 */
	function parse_block( $block ) {
		
		// reset some vars
		$this->have = array('' => false);
		foreach( $this->properties as $p => $v ) {
			$this->have[$p]  = false;
		}
		
		// explode to declarations
		$declarations = explode( ";", $block );
		
		// prepare return array
		$return = array();
		
		// loop
		foreach( $declarations as $declaration ) {
			$declaration = preg_replace('/\\/\\*.*\\*\\//', '', $declaration); // remove comments
			if( !$declaration ) continue;
			$declaration = trim($declaration) . ';';
			preg_replace( '/^[\s\t]*([a-z\-]+)\:[\s\t]*(.+)[\s\t]*;/', '$1: $2;', $declaration );
			$d = $this->parse_declaration( $declaration );
			if( $d ) {
				$return[] = '	'.$d;
			}
		}
		// check for unassigned right/left
		foreach( $this->have as $p => $v ) {
			if( !$v ) continue;
			$d = ($v == 'right') ? 'right' : 'left';
			if( $p == '' ) {
				$return[] = "\t$d: auto;";
			} else {
				$return[] = "\t$p-$d: {$this->properties[$p]};";
			}
		}
		
		// return
		return count($return) ? implode("\n", $return) : false;
	}
	
	/**
	 * extract blocks from css file, then $this->parse_block() on each.
	 */
	function parse_css($css) {
		
		/**
		 * TODO: store comments so if comment includes {} we don't get confused :S
		 */
		$comments = array();
		
		$b = explode( '}', $css );
		
		/**
		 * this return array contains values in the form:
		 * 				array( $selector, $declarations );
		 */
		$return = array();
		
		// media vals
		$is_media = false;
		$media_selector = '';
		$media_i = 0;
		
		// loop throw blocks.
		foreach( $b as $_b ) {
			
			// explode to selector and declarations.
			$_b = explode( '{', $_b );

			
			// check header to see if it's @media.
			$h = $this->remove_comments($_b[0]);
			
			if( preg_match( '/@media/', $h ) ) {
				
				$is_media = true;
				$media_selector = $_b[0];
				$media_i = 0;
				
				array_shift($_b);
				
			} elseif( count($_b) == 1 && $is_media ) {
				
				if( $media_i ) {
					$return = array_slice( $return, 0, -$media_i );
					$a = array_slice( $return, -$media_i, $media_i );
					
					$s = '';
					
					// loop throw the array
					foreach( $a as $_a ) {
						if( $_a[1] )
							$s .= "\n" . trim( $_a[0] ) . " {\n$_a[1]\n}\n";
						else
							$s .= "\n" . $_a[0];
					}
					
					$return[] = array( $media_selector, $s );
					
				} else {
					
					// lets at least add the comments
					/*
					$c = $this->keep_comments($media_selector);
					
					if( !empty( $c ) ) {
						$return[] = array( $c );
					}
					*/
					
					// or i'll keep the selector!
					$c = $media_selector;
					
					if( !empty( $c ) ) {
						$return[] = array( $c , '' );
					}
				}
				
				$is_media = false;
				$madia_selector = '';
				$media_i = 0;
				
				continue;
			} elseif( preg_match( '/^\\.f[rln]$/', trim($h) ) ) {
				echo 'yes';
				//leave comments alone!
				$c = $this->keep_comments($_b[0]);
				if( !empty( $c ) ) {
					$return[] = array( $c );
				}
				
				// continue
				continue;
			}
			
			// parse declarations
			$t = $this->parse_block( $_b[1] );
			
			// add to the $return array
			if( $t ) {
				$media_i++;
				$return[] = array( $this->right_to_left($_b[0]), $t );
			} else {
				
				//leave comments alone!
				$c = $this->keep_comments($_b[0]);
				if( !empty( $c ) ) {
					$return[] = array( $c );
				}
				
			}
		}
		
		// return string
		$x = '';
		
		// loop throw the array
		foreach( $return as $r ) {
			if( count($r)>1 )
				$x .= "\n" . trim( $r[0] ) . " {\n$r[1]\n}\n";
			else
				$x .= "\n" . $r[0];
		}
		
		//remove 3+ empty lines
		$x = preg_replace( '/(\n)\n+/', '$1$1', $x );
		
		// first char is an empty line!
		$x = preg_replace( '/^\n+/', '', $x );
		
		if( empty($x) )
			return false;
		
		// add some credits
		$x .= "\n\n/* Generated by the RTLer - http://wordpress.org/extend/plugins/rtler/ */";
		
		// Done. whew!
		return $x;
	}
	
	/**
	 * remove the css comments from a string.
	 */
	function remove_comments($string) {
		
		// first, remove the //comments
		$s = explode( "\n", $string );
		$r = array();
		foreach( $s as $_s ) {
			$_s = trim( $_s );
			if( substr( $_s, 0, 2 ) != '//' ) {
				$r[] = $_s;
			}
		}
		$s = implode( "\n", $r );
		
		// now, remove the /*comments*/
		$s = explode( '*/', $s );
		$r = array();
		foreach( $s as $_s ) {
			$t = explode( '/*', $_s );
			if( !empty( $t[0] ) ) {
				$r[] = $t[0];
			}
		}
		
		// and return
		return implode( "\n", $r );
	}
	
	/**
	 * remove everything except the comments from a string.
	 */
	function keep_comments($string) {
		
		// look for /*comments*/
		$s = explode( '*/', $string );
		$x = '';
		
		foreach( $s as $_s ) {
			$t = explode( '/*', $_s );
			if( count( $t )>1 ) {
				$x .= "/*{$t[1]}*/\n";
			}
		}
		
		// and return
		return $x;
	}
	
	/**
	 * replace "right" width "left" and vice versa
	 */
	function right_to_left($str) {
		
		// replace left with a TMP string.
		$s = str_replace( 'left', 'TMP_LEFT_STR', $str );
		
		// flip right to left.
		$s = str_replace( 'right', 'left', $s );
		
		// flip left to right.
		$s = str_replace( 'TMP_LEFT_STR', 'right', $s );
		
		// return
		return $s;
	}
	
}