<?php


namespace CleantalkSP\SpbctWP\Scanner\Heuristic;


use CleantalkSP\DataStructures\ExtendedSplFixedArray;
use CleantalkSP\SpbctWP\Scanner\Heuristic\DataStructures\Token;

/**
 * Array with token
 * [
 *    0 => (string) TOKEN_TYPE,
 *    1 => (mixed)  TOKEN_VALUE
 *    2 => (int)    DOCUMENT_STRING_NUMBER
 * ]
 *
 * @property Token|null $prev4
 * @property Token|null $prev3
 * @property Token|null $prev2
 * @property Token|null $prev1
 * @property Token|null $current
 * @property Token|null $next1
 * @property Token|null $next2
 * @property Token|null $next3
 * @property Token|null $next4
 */
class Tokens implements \Iterator
{
    private $position = 0;
    
    /**
     * @var ExtendedSplFixedArray of \CleantalkSP\SpbctWP\Scanner\Heuristic\DataStructures\Token
     * [
     *    0 => (string) TOKEN_TYPE,
     *    1 => (mixed)  TOKEN_VALUE
     *    2 => (int)    DOCUMENT_STRING_NUMBER
     * ]
     */
    public $tokens;
    
    /**
     * @var int
     */
    private $max_index;
    
    /**
     * @var array of arrays without code
     * Contain tokens with comments, HTML and so on
     * [
     *    0 => (string) TOKEN_TYPE,
     *    1 => (mixed)  TOKEN_VALUE
     *    2 => (int)    DOCUMENT_STRING_NUMBER
     * ]
     */
    public $comments = array();
    
    /**
     * @var array of arrays with HTML
     * [
     *    0 => (string) TOKEN_TYPE,
     *    1 => (mixed)  TOKEN_VALUE
     *    2 => (int)    DOCUMENT_STRING_NUMBER
     * ]
     */
    public $html = array();
    
    /**
     * @var TokenGroups
     */
    private $groups;
    
    public function __construct( $content )
    {
        $this->groups = new TokenGroups();
        $this->getTokensFromText($content);
    }
    
    /**
     * Parse code and transform it to array of arrays with token like
     * [
     *    0 => (string) TOKEN_TYPE,
     *    1 => (mixed)  TOKEN_VALUE
     *    2 => (int)    DOCUMENT_STRING_NUMBER
     * ]
     *
     * Convert all tokens to the above mentioned format
     *
     * Single tokens like '.' or ';' receive TOKEN_TYPE like '__SERV'
     * Single tokens like '.' or ';' receive DOCUMENT_STRING_NUMBER from the previous token
     *
     * @param $text
     *
     * @return void
     */
    public function getTokensFromText( $text )
    {
        $this->tokens = ExtendedSplFixedArray::createFromArray( @token_get_all( $text ) );
        $this->convertTokensToStandard();
    }
    
    /**
     * Work with $this->tokens
     * 
     * Standardizing all tokens to $this->tokens[N][
     *    0 => (string) TOKEN_TYPE,
     *    1 => (mixed)  TOKEN_VALUE
     *    2 => (int)    DOCUMENT_STRING_NUMBER
     * ]
     *
     * @return void
     */
    private function convertTokensToStandard()
    {
        // We are using for instead of foreach because we might stumble on SplFixedArray.
        // SplFixedArray doesn't support passing element by reference in 'for' cycles.
        for(
            
            // Initialization
            $key             = 0,
            $prev_token_line = 1,
            $length          = count($this->tokens);
            
            // Before each iteration
            $key < $length;
            
            // After each iteration
            $prev_token_line = $this->tokens[$key]->line, // Set previous token to compile next service(__SERV) tokens
            $key++
        ){
            
            $curr_token = $this->tokens[$key]; // Set current iteration token
            
            $this->tokens[ $key ] = is_scalar($curr_token)
                ? new Token('__SERV',              $curr_token,    $prev_token_line) // For simple tokens like ';', ','...
                : new Token(token_name($curr_token[0]), $curr_token[1], $curr_token[2]);  // For normal token with type
        }
    }
    
    public function setMaxKey(){
        $this->max_index = $this->tokens->getSize();
    }
    
    /**
     * set tokens from next{$depth} to prev{$depth}
     *
     * @param int $depth
     */
    public function setIterationTokens($depth = 4)
    {
        // Set current token
        $this->current = $this->tokens[$this->position];
        
        // Set previous tokens
        for( $i = $depth; $i !== 0; $i-- ){
            $this->{'prev'.$i} = $this->getToken( 'prev', $i );
        }
        
        // Set next tokens
        for( $i = $depth; $i !== 0; $i-- ){
            $this->{'next'.$i} = $this->getToken( 'next', $i );
        }
    }
    
    
    /**
     * Gather tokens back in string
     * Using all tokens if non passed
     *
     * @param array|ExtendedSplFixedArray $input Array of lexems
     *
     * @return string
     */
	public function glueTokens( $input = array() )
    {
        $input = $input ?: $this->tokens;
        
        return $input instanceof ExtendedSplFixedArray
	        ? implode('', $input->getColumn( 1 ) )
	        : implode('', array_column( $input, 1 ) );
    }
    
    public function isInGroup($group, $token_or_direction, $steps = 1)
    {
        $group = is_array($group)
            ? $group
            : $this->groups->$group;
        
        /** @var Token $token */
        $token = $token_or_direction instanceof Token
            ? $token_or_direction
            : $this->getToken($token_or_direction, $steps);
        
        return in_array($token->type, $group, true);
    }
    
    public function isNextTokenTypeOfGroup( $group, $steps = 1){
        $group .= '__token_group';
        $token = $this->{'next'.$steps}; // Initiating __get() method
        return isset($token[0], $this->$group) && in_array($token[0], $this->$group, true );
    }
    
    public function isPrevTokenTypeOfGroup( $group, $steps = 1){
        $group .= '__token_group';
        $token = $this->{'prev'.$steps}; // Initiating __get() method
        return isset($token[0], $this->$group) && in_array($token[0], $this->$group, true );
    }
    
    /**
     * Check if the current token is type of given string
     *
     * @param $token_type
     * @param $token
     *
     * @return bool
     */
    public function isTypeOf($token_type, $token)
    {
        return $token[0] === $token_type;
    }
    
    /**
     * Check if the current token is type of given string
     *
     * @param $token_type
     *
     * @return bool
     */
    public function isCurrentTypeOf($token_type)
    {
        return $this->current->type === $token_type;
    }
    
    /**
     * Check if the current token is type of given string
     *
     * @param string $token_type
     * @param int    $step
     *
     * @return bool
     */
    public function isNextTypeOf($token_type, $step = 1)
    {
        return $this->isTypeOf($this->{'next' . $step}, $token_type);
    }
    
    
    /**
     * Check if the current token is type of given string
     *
     * @param string $token_type
     * @param int    $step
     *
     * @return bool
     */
    public function isPrevTypeOf($token_type, $step = 1)
    {
        return $this->isTypeOf($this->{'prev' . $step}, $token_type);
    }
    
    /**
     * Compares token value to given value
     *
     * @param array|null   $token
     * @param string|array $stings_to_compare_to
     *
     * @return bool
     */
    public function isTokenEqualTo($token, $stings_to_compare_to)
    {
        return isset($token[1]) && in_array($token[1], (array)$stings_to_compare_to, true);
    }
    
    /**
     * Compares next token value to given value
     *
     * @param string|array $string_or_array
     *
     * @return bool
     */
    public function isCurrentEqualTo($string_or_array)
    {
        return $this->isTokenEqualTo($this->current, $string_or_array);
    }
    
    /**
     * Compares next token value to given value
     *
     * @param string|array $string_or_array
     * @param int          $steps
     *
     * @return bool
     */
    public function isNextEqualTo($string_or_array, $steps = 1)
    {
        return $this->isTokenEqualTo($this->{'next'.$steps}, $string_or_array);
    }
    
    /**
     * Compares previous token value to given value
     *
     * @param string|array $string_or_array
     * @param int          $steps
     *
     * @return bool
     */
    public function isPrevEqualTo($string_or_array, $steps = 1)
    {
        return $this->isTokenEqualTo($this->{'prev'.$steps}, $string_or_array);
    }
    
    /**
     * Returns position of the searched token
     * Search for needle === if needle is set
     *
     * @param              $start
     * @param string|array $needle
     * @param int          $depth of search. How far we should look for the token
     *
     * @return false|int Position of the needle
     */
    public function searchForward($start, $needle, $depth = 250)
    {
        // Needle is an array with strings
        if( is_array($needle) || $needle instanceof ExtendedSplFixedArray){
            for( $i = 0, $key = $start + 1; $i < $depth; $i++, $key++ ){
                if( isset($this->tokens[$key]) && in_array($this->tokens[$key][1], $needle, true) ){
                    return $key;
                }
            }
    
        // Needle is a string
        }else{
            for( $i = 0, $key = $start + 1; $i < $depth; $i++, $key++ ){
                if( isset($this->tokens[$key]) && $this->tokens[$key][1] === $needle ){
                    return $key;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Getting prev set lexem, Search for needle === if needle is set
     *
     * @param int          $start
     * @param string|array $needle
     * @param int          $depth of search. How far we should look for the token
     *
     * @return bool|int
     */
    public function searchBackward($start, $needle, $depth = 250)
    {
        // Needle is an array with strings
        if( is_array($needle) ){
            for( $i = 0, $key = $start - 1; $i < $depth && $key > 0; $i--, $key-- ){
                if( isset($this->tokens[$key]) && in_array($this->tokens[$key][1], $needle, true) ){
                    return $key;
                }
            }
            
        // Needle is a string
        }else{
            for( $i = 0, $key = $start - 1; $i < $depth && $key > 0; $i--, $key-- ){
                if( isset($this->tokens[$key]) && $this->tokens[$key][1] === $needle ){
                    return $key;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get next or previous token from $this->tokens
     * Try to get a token ignoring empty tokens until
     *      max key is reached ('next' direction)
     *      or
     *      zero key is reached ('prev' direction)
     *
     * @param string   $direction 'next' or 'prev' string
     * @param int      $offset    offset from the current token token
     * @param int|null $key
     *
     * @return array|null
     */
    public function getToken($direction, $offset = 0, $key = null)
    {
        $offset      = (int)$offset;
        $out         = new Token(null, null, null);
        $current_key = isset($key)
            ? $key
            : $this->position;
        
        switch($direction){
            case 'next':
                for( $current_key++, $current_offset = 0; $out->isEmpty() && $current_key <= $this->max_index; $current_key++ ){
                    $current_offset = isset($this->tokens[$current_key]) ? ++$current_offset           : $current_offset;
                    $out            = $current_offset === $offset        ? $this->tokens[$current_key] : $out;
                }
                break;
            case 'prev':
                for( $current_key--, $current_offset = 0; $out->isEmpty() && $current_key >= 0; $current_key-- ){
                    $current_offset = isset($this->tokens[$current_key]) ? ++$current_offset           : $current_offset;
                    $out            = $current_offset === $offset        ? $this->tokens[$current_key] : $out;
                    //var_dump( $out);
                }
                break;
        }
        
        return $out;
    }
    
    /**
     * Get the token with passed position (key)
     *
     * @param int|string|null $position
     *
     * @return Token|null
     */
    public function getTokenFromPosition( $position = null, $get_only_actual = false )
    {
        // If no position was requested, return current token
        if( ! isset($position) || $position === 'current' ){
            return $this->current;
        }
        
        $out = false;
        
        // Search forward for first actual token
        for( ; $out === false && $position <= $this->max_index; $position++ ){
            $out = isset($this->tokens[ $position ])
                ? $this->tokens[ $position ]
                : null;
        }
        
        return $out;
    }
    
    /**
     * Get slice from the current tokens
     *
     * @param int  $start    Start key
     * @param int  $end      End key
     * @param bool $clean_up Should we clean from null values?
     *
     * @return Token[]|false
     */
    public function getRange( $start, $end, $clean_up = true)
    {
        if( $start !== false && $end !== false ){
            
            return $this->tokens->slice(
                $start,
                $end,
                $clean_up
            );
        }
        
        return false;
    }
    
    /**
     * Unset token with given names
     *
     * @todo rename to 'unset'
     *
     * @param mixed ...$tokens_positions
     */
    public function unsetTokens(...$tokens_positions)
    {
        foreach( $tokens_positions as $tokens_position ){
            
            if( $tokens_position === 'current' ){
                $key = $this->position;
                
            }else{
                $direction = substr($tokens_position, 0, 4);
                $depth     = substr($tokens_position, 4);
                $key       = $direction === 'next'
                    ? $this->position + $depth
                    : $this->position - $depth;
            }
            unset($this->tokens[$key]);
            
        }
        
        // Resetting token from prev4 to next4
        //if( ! in_array('current', $tokens_positions, true) ){
        //    $this->setIterationTokens();
        //}
    }
    
    /**
     * Compare passed sequence of tokens to the set of token we are work on.
     * Since all token are standardized we don't have to check guess if the token from the set is array or not.
     *
     * @param int   $position
     * @param array $sequence Array of lexemes
     *
     * @return bool
     */
    public function checkSequenceFromPosition( $position, $sequence ){
        
        foreach( $sequence as $sequence_offset => $token_from_sequence ){
            
            $position_to_check = $position + $sequence_offset;
            $token_from_set  = $this->getTokenFromPosition($position_to_check, true);
            
            if(
                ! $token_from_set ||                                                                   // If the token from the set is not present
                ! in_array($token_from_set[0], (array) $token_from_sequence[0], true) ||          // Compare first element
                ( isset( $token_from_sequence[1] ) && $token_from_sequence[1] !== $token_from_set[1] ) // Compare second if provided
            ){
                return false;
            }
        }
        
        return true;
    }
    
    public function rewind()
    {
        $this->position = 0;
        $this->max_index = $this->tokens->getSize();
    }
    
    public function key()
    {
        return $this->position;
    }
    
    public function current()
    {
        return $this->tokens[ $this->position ];
    }
    
    public function next()
    {
        $this->position++;
    }
    
    public function valid()
    {
        $valid = isset( $this->tokens[ $this->position ] );
        if( $valid ){
            $this->setIterationTokens();
        }
        
        return $valid;
    }
    
    public function reindex(){
        ExtendedSplFixedArray::reindex( $this->tokens );
    }
    
    /**
     * Process only name like 'current' and (regex) /(next|prev)\d/
     * Set if not set via getToken function
     *
     * @param $name
     *
     * @return array|null
     */
    public function __get($name)
    {
        // Process names like 'next1', 'next5', 'prev4', ...
        if( strpos( $name, 'next') !== false || strpos( $name, 'prev') !== false ){
            $this->$name = $this->getToken(
                substr($name, 0, 4),
                substr($name, 4)
            );
            
            return $this->$name;
        }
    
        // Process name 'current'
        if( $name === 'current' ){
            $this->$name = $this->tokens[$this->position];
            
            return $this->$name;
        }
        
        // Get token by the given position. Name example: '_34'. Could be used for debug purposes.
        if( strpos( $name, '_')){
            $this->$name = $this->getTokenFromPosition(substr($name, 1));
            
            return $this->$name;
        }
        
        return null;
    }
    
    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->$name = $value;
    }
    
    /**
     * Process only name like 'current' and (regex) /(next|prev)\d/
     * Set if not set via getToken function
     *
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        // Process names like 'next1', 'next5', 'prev4', ...
        if( strpos($name, 'next') !== false || strpos($name, 'prev') !== false ){
            $this->$name = $this->getToken(
                substr($name, 0, 4),
                substr($name, 4)
            );
            
            return isset( $this->$name );
            
        // Process name 'current'
        }elseif( $name === 'current' ){
            $this->$name = $this->tokens[$this->position];
    
            return isset( $this->$name );
        }
        
        return false;
    }
}