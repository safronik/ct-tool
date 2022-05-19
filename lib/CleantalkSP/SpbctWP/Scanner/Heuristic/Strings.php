<?php


namespace CleantalkSP\SpbctWP\Scanner\Heuristic;


use CleantalkSP\SpbctWP\Scanner\Heuristic\DataStructures\Token;

class Strings
{
    
    public $tokens;
    
    public function __construct( Tokens $tokens_handler )
    {
        $this->tokens = $tokens_handler;
    }
    
    /**
     * Deletes T_ENCAPSED_AND_WHITESPACE
     * Coverts T_ENCAPSED_AND_WHITESPACE to T_CONSTANT_ENCAPSED_STRING if could
     *
     * @param int $key
     *
     * @return true|false Always returns false, because it doesn't unset current element
     */
    public function convertToSimple($key)
    {
        if(
            $this->tokens->next1->value === '"' &&
            $this->tokens->prev1->value === '"' &&
            $this->tokens->current->isTypeOf('T_ENCAPSED_AND_WHITESPACE')
        ){
            $this->tokens->unsetTokens('next1', 'prev1');
            $this->tokens->tokens[$key] = new Token(
                'T_CONSTANT_ENCAPSED_STRING',
                '\'' . $this->tokens->current->value . '\'',
                $this->tokens->current->line
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert chr('\xNN') to 'a'
     *
     * @param int $key
     *
     * @return false Always returns false, because it doesn't unset current element
     */
    public function convertChrFunctionToString($key)
    {
        // @todo rearrange condition sequence. Make $this->tokens->prev3->value === 'chr' first.
        if(
            $this->tokens->current->value === ')' &&
            $this->tokens->prev2->value === '(' &&
            $this->tokens->prev1->isTypeOf( 'chr_func_val' ) &&
            $this->tokens->prev3->type === 'T_STRING' &&
            $this->tokens->prev3->value === 'chr'
        ){
            $char_num     = (int)trim($this->tokens->prev1->value, '\'"');
            $this->tokens->tokens[$key] = new Token(
                'T_CONSTANT_ENCAPSED_STRING',
                '\'' . (chr($char_num) ?: '') . '\'',
                $this->tokens->prev3->line
            );
            $this->tokens->unsetTokens('prev1', 'prev2', 'prev3');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert chars present like "\xNN" to their symbols representation
     *
     * @param int $key
     *
     * @return false Always return false, do not change token structure ever
     */
    public function convertHexSymbolsToString($key)
    {
        if(
            $this->tokens->current->type === 'T_CONSTANT_ENCAPSED_STRING' &&
            // Compare first letter of a string to differ '\xNN' string from "\xNN". Quotes difference
            $this->tokens->current->value[0] === '"' &&
            preg_match('@\\\\[A-Z\d]{3}@', $this->tokens->current->value)
        ){
            preg_match_all('@(\\\\[a-zA-Z\d]{3})@', $this->tokens->current->value, $matches);
            $matches         = $matches[0];
            $replacements    = array_map(
                static function ($elem){
                    return chr( hexdec( (string) $elem) );
                },
                $matches
            );
            $this->tokens->tokens[$key][1] = str_replace($matches, $replacements, $this->tokens->tokens[$key][1]);
        }
        
        return false;
    }
    
    /**
     * Concatenates simple strings with type T_CONSTANT_ENCAPSED_STRING
     *
     * @param int $key
     *
     * @return bool true if the function unset any elements
     */
    public function concatenateSimpleStrings($key)
    {
        if(
            $this->tokens->current->isTypeOf( 'simple_strings') &&
            $this->tokens->next1  ->isTypeOf( 'simple_strings')
        ){
            $this->tokens->tokens[$key] = new Token(
                'T_CONSTANT_ENCAPSED_STRING',
                "'" . trim($this->tokens->current->value, '\'"') . trim($this->tokens->next1->value, '\'"') . "'",
                $this->tokens->current->line
            );
            $this->tokens->unsetTokens('next1');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Concatenates 'a'.'b' and "a"."b" to 'ab'
     *
     * @param int $key
     *
     * @return false Always returns false, because it doesn't unset current element
     */
    public function concatenateComplexStrings($key)
    {
        if(
            $this->tokens->current->value === '.' &&
            $this->tokens->prev1->isTypeOf('could_be_concatenated') &&
            $this->tokens->next1->isTypeOf('could_be_concatenated')
        ){
                $left_val =  $this->tokens->prev1->type === 'T_CONSTANT_ENCAPSED_STRING'
                    ? $this->tokens->prev1->value[0] === '"'
                        ? preg_replace( ['/\\\\"/',"/'/"], ['"', "\'"], substr( $this->tokens->prev1->value, 1, -1))
                        : substr($this->tokens->prev1->value, 1, -1)
                    : $this->tokens->prev1->value;
                
                $right_val =  $this->tokens->next1->type === 'T_CONSTANT_ENCAPSED_STRING'
                    ? $this->tokens->next1->value[0] === '"'
                        ? preg_replace( ['/\\\\"/',"/'/"], ['"', "\'"], substr( $this->tokens->next1->value, 1, -1))
                        : substr($this->tokens->next1->value, 1, -1)
                    : $this->tokens->next1->value;
                
                $this->tokens->tokens[$key] = new Token(
                    'T_CONSTANT_ENCAPSED_STRING',
                    "'" . $left_val . $right_val . "'",
                    $this->tokens->prev1->line
                );
                $this->tokens->unsetTokens('prev1', 'next1');
                
                return true;
            }
        
        return false;
    }
}