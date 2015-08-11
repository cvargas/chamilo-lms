<?php
/* For licensing terms, see /license.txt */
/**
 * This is the text library for Chamilo.
 * It is loaded during the global initialization,
 * so the functions below are available everywhere.
 *
 * @package chamilo.library
 */

define('EXERCISE_NUMBER_OF_DECIMALS', 2);

/**
 * This function strips all html-tags found in the input string and outputs a pure text.
 * Mostly, the function is to be used before language or encoding detection of the input string.
 * @param  string $string    The input string with html-tags to be converted to plain text.
 * @return string            The returned plain text as a result.
 */
function api_html_to_text($string)
{
    // These purifications have been found experimentally, for nice looking output.
    $string = preg_replace('/<br[^>]*>/i', "\n", $string);
    $string = preg_replace('/<\/?(div|p|h[1-6]|table|ol|ul|blockquote)[^>]*>/i', "\n", $string);
    $string = preg_replace('/<\/(tr|li)[^>]*>/i', "\n", $string);
    $string = preg_replace('/<\/(td|th)[^>]*>/i', "\t", $string);

    $string = strip_tags($string);

    // Line endings unification and cleaning.
    $string = str_replace(array("\r\n", "\n\r", "\r"), "\n", $string);
    $string = preg_replace('/\s*\n/', "\n", $string);
    $string = preg_replace('/\n+/', "\n", $string);

    return trim($string);
}

/**
 * Detects encoding of html-formatted text.
 * @param  string $string                The input html-formatted text.
 * @return string                        Returns the detected encoding.
 */
function api_detect_encoding_html($string) {
    if (@preg_match('/<head.*(<meta[^>]*content=[^>]*>).*<\/head>/si', $string, $matches)) {
        if (@preg_match('/<meta[^>]*charset=(.*)["\';][^>]*>/si', $matches[1], $matches)) {
            return api_refine_encoding_id(trim($matches[1]));
        }
    }
    return api_detect_encoding(api_html_to_text($string));
}

/**
 * Converts the text of a html-document to a given encoding, the meta-tag is changed accordingly.
 * @param string $string                The input full-html document.
 * @param string                        The new encoding value to be set.
 */
function api_set_encoding_html(&$string, $encoding) {
    $old_encoding = api_detect_encoding_html($string);
    if (@preg_match('/(.*<head.*)(<meta[^>]*content=[^>]*>)(.*<\/head>.*)/si', $string, $matches)) {
        $meta = $matches[2];
        if (@preg_match("/(<meta[^>]*charset=)(.*)([\"';][^>]*>)/si", $meta, $matches1)) {
            $meta = $matches1[1] . $encoding . $matches1[3];
            $string = $matches[1] . $meta . $matches[3];
        } else {
            $string = $matches[1] . '<meta http-equiv="Content-Type" content="text/html; charset='.$encoding.'"/>' . $matches[3];
        }
    } else {
        $count = 1;
        $string = str_ireplace('</head>', '<meta http-equiv="Content-Type" content="text/html; charset='.$encoding.'"/></head>', $string, $count);
    }
    $string = api_convert_encoding($string, $encoding, $old_encoding);
}

/**
 * Returns the title of a html document.
 * @param string $string                The contents of the input document.
 * @param string $input_encoding        The encoding of the input document. If the value is not set, it is detected.
 * @param string $$output_encoding      The encoding of the retrieved title. If the value is not set, the system encoding is assumend.
 * @return string                       The retrieved title, html-entities and extra-whitespace between the words are cleaned.
 */
function api_get_title_html(&$string, $output_encoding = null, $input_encoding = null) {
    if (@preg_match('/<head.+<title[^>]*>(.*)<\/title>/msi', $string, $matches)) {
        if (empty($output_encoding)) {
            $output_encoding = api_get_system_encoding();
        }
        if (empty($input_encoding)) {
            $input_encoding = api_detect_encoding_html($string);
        }
        return trim(@preg_replace('/\s+/', ' ', api_html_entity_decode(api_convert_encoding($matches[1], $output_encoding, $input_encoding), ENT_QUOTES, $output_encoding)));
    }
    return '';
}


/* XML processing functions */

// A regular expression for accessing declared encoding within xml-formatted text.
// Published by Steve Minutillo,
// http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss/
define('_PCRE_XML_ENCODING', '/<\?xml.*encoding=[\'"](.*?)[\'"].*\?>/m');

/**
 * Detects encoding of xml-formatted text.
 * @param string $string                The input xml-formatted text.
 * @param string $default_encoding      This is the default encoding to be returned if there is no way the xml-text's encoding to be detected. If it not spesified, the system encoding is assumed then.
 * @return string                       Returns the detected encoding.
 * @todo The second parameter is to be eliminated. See api_detect_encoding_html().
 */
function api_detect_encoding_xml($string, $default_encoding = null) {
    if (preg_match(_PCRE_XML_ENCODING, $string, $matches)) {
        return api_refine_encoding_id($matches[1]);
    }
    if (api_is_valid_utf8($string)) {
        return 'UTF-8';
    }
    if (empty($default_encoding)) {
        $default_encoding = _api_mb_internal_encoding();
    }
    return api_refine_encoding_id($default_encoding);
}


/**
 * Converts character encoding of a xml-formatted text. If inside the text the encoding is declared, it is modified accordingly.
 * @param string $string                    The text being converted.
 * @param string $to_encoding               The encoding that text is being converted to.
 * @param string $from_encoding (optional)  The encoding that text is being converted from. If it is omited, it is tried to be detected then.
 * @return string                           Returns the converted xml-text.
 */
function api_convert_encoding_xml($string, $to_encoding, $from_encoding = null) {
    return _api_convert_encoding_xml($string, $to_encoding, $from_encoding);
}

/**
 * Converts character encoding of a xml-formatted text into UTF-8. If inside the text the encoding is declared, it is set to UTF-8.
 * @param string $string                    The text being converted.
 * @param string $from_encoding (optional)  The encoding that text is being converted from. If it is omited, it is tried to be detected then.
 * @return string                           Returns the converted xml-text.
 */
function api_utf8_encode_xml($string, $from_encoding = null) {
    return _api_convert_encoding_xml($string, 'UTF-8', $from_encoding);
}

/**
 * Converts character encoding of a xml-formatted text from UTF-8 into a specified encoding. If inside the text the encoding is declared, it is modified accordingly.
 * @param string $string                    The text being converted.
 * @param string $to_encoding (optional)    The encoding that text is being converted to. If it is omited, the platform character set is assumed.
 * @return string                           Returns the converted xml-text.
 */
function api_utf8_decode_xml($string, $to_encoding = 'UTF-8') {
    return _api_convert_encoding_xml($string, $to_encoding, 'UTF-8');
}

/**
 * Converts character encoding of a xml-formatted text. If inside the text the encoding is declared, it is modified accordingly.
 * @param string $string                    The text being converted.
 * @param string $to_encoding               The encoding that text is being converted to.
 * @param string $from_encoding (optional)  The encoding that text is being converted from. If the value is empty, it is tried to be detected then.
 * @return string                           Returns the converted xml-text.
 */
function _api_convert_encoding_xml(&$string, $to_encoding, $from_encoding) {
    if (empty($from_encoding)) {
        $from_encoding = api_detect_encoding_xml($string);
    }
    $to_encoding = api_refine_encoding_id($to_encoding);
    if (!preg_match('/<\?xml.*\?>/m', $string, $matches)) {
        return api_convert_encoding('<?xml version="1.0" encoding="'.$to_encoding.'"?>'."\n".$string, $to_encoding, $from_encoding);
    }
    if (!preg_match(_PCRE_XML_ENCODING, $string)) {
        if (strpos($matches[0], 'standalone') !== false) {
            // The encoding option should precede the standalone option, othewise DOMDocument fails to load the document.
            $replace = str_replace('standalone', ' encoding="'.$to_encoding.'" standalone' , $matches[0]);
        } else {
            $replace = str_replace('?>', ' encoding="'.$to_encoding.'"?>' , $matches[0]);
        }
        return api_convert_encoding(str_replace($matches[0], $replace, $string), $to_encoding, $from_encoding);
    }
    global $_api_encoding;
    $_api_encoding = api_refine_encoding_id($to_encoding);
    return api_convert_encoding(preg_replace_callback(_PCRE_XML_ENCODING, '_api_convert_encoding_xml_callback', $string), $to_encoding, $from_encoding);
}

/**
 * A callback for serving the function _api_convert_encoding_xml().
 * @param array $matches    Input array of matches corresponding to the xml-declaration.
 * @return string           Returns the xml-declaration with modified encoding.
 */
function _api_convert_encoding_xml_callback($matches) {
    global $_api_encoding;
    return str_replace($matches[1], $_api_encoding, $matches[0]);
}

/* Functions for supporting ASCIIMathML mathematical formulas and ASCIIsvg maathematical graphics */

/**
 * Dectects ASCIIMathML formula presence within a given html text.
 * @param string $html      The input html text.
 * @return bool             Returns TRUE when there is a formula found or FALSE otherwise.
 */
function api_contains_asciimathml($html) {
    if (!preg_match_all('/<span[^>]*class\s*=\s*[\'"](.*?)[\'"][^>]*>/mi', $html, $matches)) {
        return false;
    }
    foreach ($matches[1] as $string) {
        $string = ' '.str_replace(',', ' ', $string).' ';
        if (preg_match('/\sAM\s/m', $string)) {
            return true;
        }
    }
    return false;
}

/**
 * Dectects ASCIIsvg graphics presence within a given html text.
 * @param string $html      The input html text.
 * @return bool             Returns TRUE when there is a graph found or FALSE otherwise.
 */
function api_contains_asciisvg($html) {
    if (!preg_match_all('/<embed([^>]*?)>/mi', $html, $matches)) {
        return false;
    }
    foreach ($matches[1] as $string) {
        $string = ' '.str_replace(',', ' ', $string).' ';
        if (preg_match('/sscr\s*=\s*[\'"](.*?)[\'"]/m', $string)) {
            return true;
        }
    }
    return false;
}

/* Miscellaneous text processing functions */

/**
 * Convers a string from camel case into underscore.
 * Works correctly with ASCII strings only, implementation for human-language strings is not necessary.
 * @param string $string                            The input string (ASCII)
 * @return string                                   The converted result string
 */
function api_camel_case_to_underscore($string) {
    return strtolower(preg_replace('/([a-z])([A-Z])/', "$1_$2", $string));
}

/**
 * Converts a string with underscores into camel case.
 * Works correctly with ASCII strings only, implementation for human-language strings is not necessary.
 * @param string $string                            The input string (ASCII)
 * @param bool $capitalise_first_char (optional)    If true (default), the function capitalises the first char in the result string.
 * @return string                                   The converted result string
 */
function api_underscore_to_camel_case($string, $capitalise_first_char = true) {
    if ($capitalise_first_char) {
        $string = ucfirst($string);
    }
    return preg_replace_callback('/_([a-z])/', '_api_camelize', $string);
}

// A function for internal use, only for this library.
function _api_camelize($match) {
    return strtoupper($match[1]);
}

/**
 * Truncates a string.
 *
 * @author Brouckaert Olivier
 * @param  string $text                  The text to truncate.
 * @param  integer $length               The approximate desired length. The length of the suffix below is to be added to have the total length of the result string.
 * @param  string $suffix                A suffix to be added as a replacement.
 * @param string $encoding (optional)    The encoding to be used. If it is omitted, the platform character set will be used by default.
 * @param  boolean $middle               If this parameter is true, truncation is done in the middle of the string.
 * @return string                        Truncated string, decorated with the given suffix (replacement).
 */
function api_trunc_str($text, $length = 30, $suffix = '...', $middle = false, $encoding = null) {
    if (empty($encoding)) {
        $encoding = api_get_system_encoding();
    }
    $text_length = api_strlen($text, $encoding);
    if ($text_length <= $length) {
        return $text;
    }
    if ($middle) {
        return rtrim(api_substr($text, 0, round($length / 2), $encoding)).$suffix.ltrim(api_substr($text, - round($length / 2), $text_length, $encoding));
    }
    return rtrim(api_substr($text, 0, $length, $encoding)).$suffix;
}

/**
 * Handling simple and double apostrofe in order that strings be stored properly in database
 *
 * @author Denes Nagy
 * @param  string variable - the variable to be revised
 */
function domesticate($input) {
    $input = stripslashes($input);
    $input = str_replace("'", "''", $input);
    $input = str_replace('"', "''", $input);
    return ($input);
}

/**
 * function make_clickable($string)
 *
 * @desc   Completes url contained in the text with "<a href ...".
 *         However the function simply returns the submitted text without any
 *         transformation if it already contains some "<a href:" or "<img src=".
 * @param string $text text to be converted
 * @return text after conversion
 * @author Rewritten by Nathan Codding - Feb 6, 2001.
 *         completed by Hugues Peeters - July 22, 2002
 *
 * Actually this function is taken from the PHP BB 1.4 script
 * - Goes through the given string, and replaces xxxx://yyyy with an HTML <a> tag linking
 *     to that URL
 * - Goes through the given string, and replaces www.xxxx.yyyy[zzzz] with an HTML <a> tag linking
 *     to http://www.xxxx.yyyy[/zzzz]
 * - Goes through the given string, and replaces xxxx@yyyy with an HTML mailto: tag linking
 *        to that email address
 * - Only matches these 2 patterns either after a space, or at the beginning of a line
 *
 * Notes: the email one might get annoying - it's easy to make it more restrictive, though.. maybe
 * have it require something like xxxx@yyyy.zzzz or such. We'll see.
 */

/**
 * Callback to convert URI match to HTML A element.
 *
 * This function was backported from 2.5.0 to 2.3.2. Regex callback for {@link
 * make_clickable()}.
 *
 * @since Wordpress 2.3.2
 * @access private
 *
 * @param array $matches Single Regex Match.
 * @return string HTML A element with URI address.
 */
function _make_url_clickable_cb($matches) {
    $url = $matches[2];

    if ( ')' == $matches[3] && strpos( $url, '(' ) ) {
        // If the trailing character is a closing parethesis, and the URL has an opening parenthesis in it, add the closing parenthesis to the URL.
        // Then we can let the parenthesis balancer do its thing below.
        $url .= $matches[3];
        $suffix = '';
    } else {
        $suffix = $matches[3];
    }

    // Include parentheses in the URL only if paired
    while ( substr_count( $url, '(' ) < substr_count( $url, ')' ) ) {
        $suffix = strrchr( $url, ')' ) . $suffix;
        $url = substr( $url, 0, strrpos( $url, ')' ) );
    }

    $url = esc_url($url);
    if ( empty($url) )
        return $matches[0];

    return $matches[1] . "<a href=\"$url\" rel=\"nofollow\">$url</a>" . $suffix;
}

/**
 * Checks and cleans a URL.
 *
 * A number of characters are removed from the URL. If the URL is for displaying
 * (the default behaviour) ampersands are also replaced. The 'clean_url' filter
 * is applied to the returned cleaned URL.
 *
 * @since wordpress 2.8.0
 * @uses wp_kses_bad_protocol() To only permit protocols in the URL set
 *		via $protocols or the common ones set in the function.
 *
 * @param string $url The URL to be cleaned.
 * @param array $protocols Optional. An array of acceptable protocols.
 *		Defaults to 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'svn' if not set.
 * @param string $_context Private. Use esc_url_raw() for database usage.
 * @return string The cleaned $url after the 'clean_url' filter is applied.
 */
function esc_url( $url, $protocols = null, $_context = 'display' ) {
    //$original_url = $url;

    if ( '' == $url )
        return $url;
    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);
    $strip = array('%0d', '%0a', '%0D', '%0A');
    $url = _deep_replace($strip, $url);
    $url = str_replace(';//', '://', $url);
    /* If the URL doesn't appear to contain a scheme, we
     * presume it needs http:// appended (unless a relative
     * link starting with /, # or ? or a php file).
     */
    if ( strpos($url, ':') === false && ! in_array( $url[0], array( '/', '#', '?' ) ) &&
        ! preg_match('/^[a-z0-9-]+?\.php/i', $url) )
        $url = 'http://' . $url;

    return Security::remove_XSS($url);

    /*// Replace ampersands and single quotes only when displaying.
    if ( 'display' == $_context ) {
        $url = wp_kses_normalize_entities( $url );
        $url = str_replace( '&amp;', '&#038;', $url );
        $url = str_replace( "'", '&#039;', $url );
    }

    if ( '/' === $url[0] ) {
        $good_protocol_url = $url;
    } else {
        if ( ! is_array( $protocols ) )
            $protocols = wp_allowed_protocols();
        $good_protocol_url = wp_kses_bad_protocol( $url, $protocols );
        if ( strtolower( $good_protocol_url ) != strtolower( $url ) )
            return '';
    }

    /**
     * Filter a string cleaned and escaped for output as a URL.
     *
     * @since 2.3.0
     *
     * @param string $good_protocol_url The cleaned URL to be returned.
     * @param string $original_url      The URL prior to cleaning.
     * @param string $_context          If 'display', replace ampersands and single quotes only.
     */
    //return apply_filters( 'clean_url', $good_protocol_url, $original_url, $_context );98
}


/**
 * Perform a deep string replace operation to ensure the values in $search are no longer present
 *
 * Repeats the replacement operation until it no longer replaces anything so as to remove "nested" values
 * e.g. $subject = '%0%0%0DDD', $search ='%0D', $result ='' rather than the '%0%0DD' that
 * str_replace would return
 *
 * @since wordpress  2.8.1
 * @access private
 *
 * @param string|array $search The value being searched for, otherwise known as the needle. An array may be used to designate multiple needles.
 * @param string $subject The string being searched and replaced on, otherwise known as the haystack.
 * @return string The string with the replaced svalues.
 */
function _deep_replace( $search, $subject ) {
    $subject = (string) $subject;

    $count = 1;
    while ( $count ) {
        $subject = str_replace( $search, '', $subject, $count );
    }

    return $subject;
}


/**
 * Callback to convert URL match to HTML A element.
 *
 * This function was backported from 2.5.0 to 2.3.2. Regex callback for {@link
 * make_clickable()}.
 *
 * @since wordpress  2.3.2
 * @access private
 *
 * @param array $matches Single Regex Match.
 * @return string HTML A element with URL address.
 */
function _make_web_ftp_clickable_cb($matches) {
    $ret = '';
    $dest = $matches[2];
    $dest = 'http://' . $dest;
    $dest = esc_url($dest);
    if ( empty($dest) )
        return $matches[0];

    // removed trailing [.,;:)] from URL
    if ( in_array( substr($dest, -1), array('.', ',', ';', ':', ')') ) === true ) {
        $ret = substr($dest, -1);
        $dest = substr($dest, 0, strlen($dest)-1);
    }
    return $matches[1] . "<a href=\"$dest\" rel=\"nofollow\">$dest</a>$ret";
}

/**
 * Callback to convert email address match to HTML A element.
 *
 * This function was backported from 2.5.0 to 2.3.2. Regex callback for {@link
 * make_clickable()}.
 *
 * @since wordpress  2.3.2
 * @access private
 *
 * @param array $matches Single Regex Match.
 * @return string HTML A element with email address.
 */
function _make_email_clickable_cb($matches) {
    $email = $matches[2] . '@' . $matches[3];
    return $matches[1] . "<a href=\"mailto:$email\">$email</a>";
}

/**
 * Convert plaintext URI to HTML links.
 *
 * Converts URI, www and ftp, and email addresses. Finishes by fixing links
 * within links.
 *
 * @since wordpress  0.71
 *
 * @param string $text Content to convert URIs.
 * @return string Content with converted URIs.
 */
function make_clickable( $text ) {
    $r = '';
    $textarr = preg_split( '/(<[^<>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE ); // split out HTML tags
    $nested_code_pre = 0; // Keep track of how many levels link is nested inside <pre> or <code>
    foreach ( $textarr as $piece ) {

        if ( preg_match( '|^<code[\s>]|i', $piece ) || preg_match( '|^<pre[\s>]|i', $piece ) )
            $nested_code_pre++;
        elseif ( ( '</code>' === strtolower( $piece ) || '</pre>' === strtolower( $piece ) ) && $nested_code_pre )
            $nested_code_pre--;

        if ( $nested_code_pre || empty( $piece ) || ( $piece[0] === '<' && ! preg_match( '|^<\s*[\w]{1,20}+://|', $piece ) ) ) {
            $r .= $piece;
            continue;
        }

        // Long strings might contain expensive edge cases ...
        if ( 10000 < strlen( $piece ) ) {
            // ... break it up
            foreach ( _split_str_by_whitespace( $piece, 2100 ) as $chunk ) { // 2100: Extra room for scheme and leading and trailing paretheses
                if ( 2101 < strlen( $chunk ) ) {
                    $r .= $chunk; // Too big, no whitespace: bail.
                } else {
                    $r .= make_clickable( $chunk );
                }
            }
        } else {
            $ret = " $piece "; // Pad with whitespace to simplify the regexes

            $url_clickable = '~
				([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
				(                                                      # 2: URL
					[\\w]{1,20}+://                                # Scheme and hier-part prefix
					(?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
					[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
					(?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
						[\'.,;:!?)]                            # Punctuation URL character
						[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
					)*
				)
				(\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
			~xS'; // The regex is a non-anchored pattern and does not have a single fixed starting character.
            // Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.

            $ret = preg_replace_callback( $url_clickable, '_make_url_clickable_cb', $ret );

            $ret = preg_replace_callback( '#([\s>])((www|ftp)\.[\w\\x80-\\xff\#$%&~/.\-;:=,?@\[\]+]+)#is', '_make_web_ftp_clickable_cb', $ret );
            $ret = preg_replace_callback( '#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i', '_make_email_clickable_cb', $ret );

            $ret = substr( $ret, 1, -1 ); // Remove our whitespace padding.
            $r .= $ret;
        }
    }

    // Cleanup of accidental links within links
    $r = preg_replace( '#(<a([ \r\n\t]+[^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i', "$1$3</a>", $r );
    return $r;
}

/**
 * Breaks a string into chunks by splitting at whitespace characters.
 * The length of each returned chunk is as close to the specified length goal as possible,
 * with the caveat that each chunk includes its trailing delimiter.
 * Chunks longer than the goal are guaranteed to not have any inner whitespace.
 *
 * Joining the returned chunks with empty delimiters reconstructs the input string losslessly.
 *
 * Input string must have no null characters (or eventual transformations on output chunks must not care about null characters)
 *
 * <code>
 * _split_str_by_whitespace( "1234 67890 1234 67890a cd 1234   890 123456789 1234567890a    45678   1 3 5 7 90 ", 10 ) ==
 * array (
 *   0 => '1234 67890 ',  // 11 characters: Perfect split
 *   1 => '1234 ',        //  5 characters: '1234 67890a' was too long
 *   2 => '67890a cd ',   // 10 characters: '67890a cd 1234' was too long
 *   3 => '1234   890 ',  // 11 characters: Perfect split
 *   4 => '123456789 ',   // 10 characters: '123456789 1234567890a' was too long
 *   5 => '1234567890a ', // 12 characters: Too long, but no inner whitespace on which to split
 *   6 => '   45678   ',  // 11 characters: Perfect split
 *   7 => '1 3 5 7 9',    //  9 characters: End of $string
 * );
 * </code>
 *
 * @since wordpress  3.4.0
 * @access private
 *
 * @param string $string The string to split.
 * @param int $goal The desired chunk length.
 * @return array Numeric array of chunks.
 */
function _split_str_by_whitespace( $string, $goal ) {
    $chunks = array();

    $string_nullspace = strtr( $string, "\r\n\t\v\f ", "\000\000\000\000\000\000" );

    while ( $goal < strlen( $string_nullspace ) ) {
        $pos = strrpos( substr( $string_nullspace, 0, $goal + 1 ), "\000" );

        if ( false === $pos ) {
            $pos = strpos( $string_nullspace, "\000", $goal + 1 );
            if ( false === $pos ) {
                break;
            }
        }

        $chunks[] = substr( $string, 0, $pos + 1 );
        $string = substr( $string, $pos + 1 );
        $string_nullspace = substr( $string_nullspace, $pos + 1 );
    }

    if ( $string ) {
        $chunks[] = $string;
    }

    return $chunks;
}


/**
 * @desc This function does some parsing on the text that gets inputted. This parsing can be of any kind
 *       LaTeX notation, Word Censoring, Glossary Terminology (extension will available soon), Musical Notations, ...
 *       The inspiration for this filter function came from Moodle an phpBB who both use a similar approach.
 * <code>[tex]\sqrt(2)[/tex]</code>
 * @param $input string. some text
 * @return $output string. some text that contains the parsed elements.
 * @author Patrick Cool <patrick.cool@UGent.be>
 * @version March 2OO6
 */
function text_filter($input, $filter = true) {

    //$input = stripslashes($input);

    if ($filter) {
        // ***  parse [tex]...[/tex] tags  *** //
        // which will return techexplorer or image html depending on the capabilities of the
        // browser of the user (using some javascript that checks if the browser has the TechExplorer plugin installed or not)
        //$input = _text_parse_tex($input);

        // *** parse [teximage]...[/teximage] tags *** //
        // these force the gif rendering of LaTeX using the mimetex gif renderer
        //$input=_text_parse_tex_image($input);

        // *** parse [texexplorer]...[/texexplorer] tags  *** //
        // these force the texeplorer LaTeX notation
        //$input = _text_parse_texexplorer($input);

        // *** Censor Words *** //
        // censor words. This function removes certain words by [censored]
        // this can be usefull when the campus is open to the world.
        // $input=text_censor_words($input);

        // *** parse [?]...[/?] tags *** //
        // for the glossary tool
        //$input = _text_parse_glossary($input);

        // parse [wiki]...[/wiki] tags
        // this is for the coolwiki plugin.
        // $input=text_parse_wiki($input);

        // parse [tool]...[/tool] tags
        // this parse function adds a link to a certain tool
        // $input=text_parse_tool($input);

        // parse [user]...[/user] tags

        // parse [email]...[/email] tags

        // parse [code]...[/code] tags
    }

    return $input;
}

/**
 * Applies parsing for tex commands that are separated by [tex]
 * [/tex] to make it readable for techexplorer plugin.
 * This function should not be accessed directly but should be accesse through the text_filter function
 * @param string $text    The text to parse
 * @return string         The text after parsing.
 * @author Patrick Cool <patrick.cool@UGent.be>
 * @version June 2004
 */
function _text_parse_tex($textext) {
    //$textext = str_replace(array ("[tex]", "[/tex]"), array ('[*****]', '[/*****]'), $textext);
    //$textext = stripslashes($texttext);

    $input_array = preg_split("/(\[tex]|\[\/tex])/", $textext, -1, PREG_SPLIT_DELIM_CAPTURE);

    foreach ($input_array as $key => $value) {
        if ($key > 0 && $input_array[$key - 1] == '[tex]' AND $input_array[$key + 1] == '[/tex]') {
            $input_array[$key] = latex_gif_renderer($value);
            unset($input_array[$key - 1]);
            unset($input_array[$key + 1]);
            //echo 'LaTeX: <embed type="application/x-techexplorer" texdata="'.stripslashes($value).'" autosize="true" pluginspage="http://www.integretechpub.com/techexplorer/"><br />';
        }
    }

    $output = implode('',$input_array);
    return $output;
}

/**
 * This function should not be accessed directly but should be accesse through the text_filter function
 * @author     Patrick Cool <patrick.cool@UGent.be>
 */
function _text_parse_glossary($input) {
    return $input;
}

/**
 * @desc This function makes a valid link to a different tool.
 *       This function should not be accessed directly but should be accesse through the text_filter function
 * @author Patrick Cool <patrick.cool@UGent.be>
 */
function _text_parse_tool($input) {
    // An array with all the valid tools
    $tools[] = array(TOOL_ANNOUNCEMENT, 'announcements/announcements.php');
    $tools[] = array(TOOL_CALENDAR_EVENT, 'calendar/agenda.php');

    // Check if the name between the [tool] [/tool] tags is a valid one
}

/**
 * Renders LaTeX code into a gif or retrieve a cached version of the gif.
 * @author Patrick Cool <patrick.cool@UGent.be> Ghent University
 */
function latex_gif_renderer($latex_code) {
    $_course = api_get_course_info();

    // Setting the paths and filenames
    $mimetex_path = api_get_path(LIBRARY_PATH).'mimetex/';
    $temp_path = api_get_path(SYS_COURSE_PATH).$_course['path'].'/temp/';
    $latex_filename = md5($latex_code).'.gif';

    if (!file_exists($temp_path.$latex_filename) OR isset($_GET['render'])) {
        if (IS_WINDOWS_OS) {
            $mimetex_command = $mimetex_path.'mimetex.exe -e "'.$temp_path.md5($latex_code).'.gif" '.escapeshellarg($latex_code).'';
        } else {
            $mimetex_command = $mimetex_path.'mimetex.cgi -e "'.$temp_path.md5($latex_code).'.gif" '.escapeshellarg($latex_code);
        }
        exec($mimetex_command);
        //echo 'volgende shell commando werd uitgevoerd:<br /><pre>'.$mimetex_command.'</pre><hr>';
    }

    $return  = "<a href=\"\" onclick=\"javascript: newWindow=window.open('".api_get_path(WEB_CODE_PATH)."inc/latex.php?code=".urlencode($latex_code)."&amp;filename=$latex_filename','latexCode','toolbar=no,location=no,scrollbars=yes,resizable=yes,status=yes,width=375,height=250,left=200,top=100');\">";
    $return .= '<img src="'.api_get_path(WEB_COURSE_PATH).$_course['path'].'/temp/'.$latex_filename.'" alt="'.$latex_code.'" border="0" /></a>';
    return $return;
}

/**
 * This functions cuts a paragraph
 * i.e cut('Merry Xmas from Lima',13) = "Merry Xmas fr..."
 * @param string    The text to "cut"
 * @param int       Count of chars
 * @param bool      Whether to embed in a <span title="...">...</span>
 * @return string
 * */
function cut($text, $maxchar, $embed = false) {
    if (api_strlen($text) > $maxchar) {
        if ($embed) {
            return '<span title="'.$text.'">'.api_substr($text, 0, $maxchar).'...</span>';
        }
        return api_substr($text, 0, $maxchar).' ...';
    }
    return $text;
}

/**
 * Show a number as only integers if no decimals, but will show 2 decimals if exist.
 *
 * @param mixed     Number to convert
 * @param int       Decimal points 0=never, 1=if needed, 2=always
 * @return mixed    An integer or a float depends on the parameter
 */
function float_format($number, $flag = 1) {
    if (is_numeric($number)) {
        if (!$number) {
            $result = ($flag == 2 ? '0.'.str_repeat('0', EXERCISE_NUMBER_OF_DECIMALS) : '0');
        } else {

            if (floor($number) == $number) {
                $result = number_format($number, ($flag == 2 ? EXERCISE_NUMBER_OF_DECIMALS : 0));
            } else {
                $result = number_format(round($number, 2), ($flag == 0 ? 0 : EXERCISE_NUMBER_OF_DECIMALS));
            }
        }
        return $result;
    }
}

// TODO: To be checked for correct timezone management.
/**
 * Function to obtain last week timestamps
 * @return array    Times for every day inside week
 */
function get_last_week() {
    $week = date('W');
    $year = date('Y');

    $lastweek = $week - 1;
    if ($lastweek == 0) {
        $week = 52;
        $year--;
    }

    $lastweek = sprintf("%02d", $lastweek);
    $arrdays = array();
    for ($i = 1; $i <= 7; $i++) {
        $arrdays[] = strtotime("$year"."W$lastweek"."$i");
    }
    return $arrdays;
}

/**
 * Gets the week from a day
 * @param   string   Date in UTC (2010-01-01 12:12:12)
 * @return  int      Returns an integer with the week number of the year
 */
function get_week_from_day($date) {
    if (!empty($date)) {
       $time = api_strtotime($date,'UTC');
       return date('W', $time);
    } else {
        return date('W');
    }
}


/**
 * Deprecated functions
 */

/**
 * Applies parsing the content for tex commands that are separated by [tex]
 * [/tex] to make it readable for techexplorer plugin.
 * @param string $text    The text to parse
 * @return string         The text after parsing.
 * @author Patrick Cool <patrick.cool@UGent.be>
 * @version June 2004
 */
function api_parse_tex($textext) {
    /*
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
        return str_replace(array('[tex]', '[/tex]'), array("<object classid=\"clsid:5AFAB315-AD87-11D3-98BB-002035EFB1A4\"><param name=\"autosize\" value=\"true\" /><param name=\"DataType\" value=\"0\" /><param name=\"Data\" value=\"", "\" /></object>"), $textext);
    }
    return str_replace(array('[tex]', '[/tex]'), array("<embed type=\"application/x-techexplorer\" texdata=\"", "\" autosize=\"true\" pluginspage=\"http://www.integretechpub.com/techexplorer/\">"), $textext);
    */
    return $textext;
}

/**
 * Applies parsing for tex commandos that are seperated by [tex]
 * [/tex] to make it readable for techexplorer plugin.
 * This function should not be accessed directly but should be accesse through the text_filter function
 * @param string $text    The text to parse
 * @return string         The text after parsing.
 * @author Patrick Cool <patrick.cool@UGent.be>
 * @version June 2004
 */
function _text_parse_texexplorer($textext) {
    /*
    if (strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
        $textext = str_replace(array("[texexplorer]", "[/texexplorer]"), array("<object classid=\"clsid:5AFAB315-AD87-11D3-98BB-002035EFB1A4\"><param name=\"autosize\" value=\"true\" /><param name=\"DataType\" value=\"0\" /><param name=\"Data\" value=\"", "\" /></object>"), $textext);
    } else {
        $textext = str_replace(array("[texexplorer]", "[/texexplorer]"), array("<embed type=\"application/x-techexplorer\" texdata=\"", "\" autosize=\"true\" pluginspage=\"http://www.integretechpub.com/techexplorer/\">"), $textext);
    }
    return $textext;
    */
    return $textext;
}

/**
 * This function splits the string into words and then joins them back together again one by one.
 * Example: "Test example of a long string"
 * 			substrwords(5) = Test ... *
 * @param string
 * @param int the max number of character
 * @param string how the string will be end
 * @return a reduce string
 */

function substrwords($text,$maxchar,$end='...')
{
	if(strlen($text)>$maxchar)
	{
		$words=explode(" ",$text);
		$output = '';
		$i=0;
		while(1)
		{
			$length = (strlen($output)+strlen($words[$i]));
			if($length>$maxchar)
			{
				break;
			}
			else
			{
				$output = $output." ".$words[$i];
				$i++;
			};
		};
	}
	else
	{
		$output = $text;
		return $output;
	}
	return $output.$end;
}

function implode_with_key($glue, $array) {
    if (!empty($array)) {
        $string = '';
        foreach($array as $key => $value) {
            if (empty($value)) {
                $value = 'null';
            }
            $string .= $key." : ".$value." $glue ";
        }
        return $string;
    }
    return '';
}


/**
 * Transform the file size in a human readable format.
 *
 * @param  int  $file_size    Size of the file in bytes
 * @return string A human readable representation of the file size
 */
function format_file_size($file_size)
{
    $file_size = intval($file_size);
    if ($file_size >= 1073741824) {
        $file_size = round($file_size / 1073741824 * 100) / 100 . 'G';
    } elseif($file_size >= 1048576) {
        $file_size = round($file_size / 1048576 * 100) / 100 . 'M';
    } elseif($file_size >= 1024) {
        $file_size = round($file_size / 1024 * 100) / 100 . 'k';
    } else {
        $file_size = $file_size . 'B';
    }
    return $file_size;
}

function return_datetime_from_array($array)
{
    $year	 = '0000';
    $month = $day = $hours = $minutes = $seconds = '00';
    if (isset($array['Y']) && (isset($array['F']) || isset($array['M']))  && isset($array['d']) && isset($array['H']) && isset($array['i'])) {
        $year = $array['Y'];
        $month = isset($array['F'])?$array['F']:$array['M'];
        if (intval($month) < 10 ) $month = '0'.$month;
        $day = $array['d'];
        if (intval($day) < 10 ) $day = '0'.$day;
        $hours = $array['H'];
        if (intval($hours) < 10 ) $hours = '0'.$hours;
        $minutes = $array['i'];
        if (intval($minutes) < 10 ) $minutes = '0'.$minutes;
    }
    if (checkdate($month,$day,$year)) {
        $datetime = $year.'-'.$month.'-'.$day.' '.$hours.':'.$minutes.':'.$seconds;
    }
    return $datetime;
}

/**
 * Converts an string CLEANYO[admin][amann,acostea]
 * into an array:
 *
 * array(
 *  CLEANYO
 *  admin
 *  amann,acostea
 * )
 *
 * @param $array
 * @return array
 */
function bracketsToArray($array)
{
    return preg_split('/[\[\]]+/', $array, -1, PREG_SPLIT_NO_EMPTY);
}

/**
 * @param string $string
 * @param bool $capitalizeFirstCharacter
 * @return mixed
 */
function underScoreToCamelCase($string, $capitalizeFirstCharacter = true)
{
    $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

    if (!$capitalizeFirstCharacter) {
        $str[0] = strtolower($str[0]);
    }

    return $str;
}
