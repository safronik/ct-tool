<?php


namespace CleantalkSP\SpbctWP\Scanner\Heuristic;


use CleantalkSP\SpbctWP\Scanner\Heuristic\DataStructures\Token;
use CleantalkSP\DataStructures\ExtendedSplFixedArray;

class Variables
{
    public $variables     = array();
    public $variables_bad = array();
    public $arrays        = array();
    public $constants     = array();
    
    /**
     * @var Tokens
     */
    public $tokens;
    
    private $variables_types_to_concat = array(
        'T_CONSTANT_ENCAPSED_STRING',
        // 'T_ENCAPSED_AND_WHITESPACE',
        'T_LNUMBER',
        'T_DNUMBER',
    );
    
    private $sequences = array(
        
        'define_constant' => array(
            array( 'T_STRING', 'define' ),
            array( '__SERV', '(', ),
            array( 'T_CONSTANT_ENCAPSED_STRING' ),
            array( '__SERV', ',', ),
            array( array('T_CONSTANT_ENCAPSED_STRING', 'T_LNUMBER') )
        ),
        
        'array_equation_array' => array(
            array( '__SERV', '=', ),
            array( 'T_ARRAY' ),
            array( '__SERV', '(', ),
        ),
        
        'array_equation_square_brackets' => array(
            array( '__SERV', '=', ),
            array( '__SERV', '[', ),
        )
    );
    
    public $variables_bad_default = array(
        '$_POST',
        '$_GET',
    );
    
    public function __construct( Tokens $tokens_handler )
    {
        $this->tokens = $tokens_handler;
    }
    
    /**
     * Replaces ${'string'} to $variable
     *
     * @param int $key
     *
     * @return false Always returns false, because it doesn't unset current element
     */
    public function convertVariableStrings($key)
    {
        if(
            $this->tokens->current->value === '$' &&
            $this->tokens->next1->value === '{' &&
            $this->tokens->next2->type === 'T_CONSTANT_ENCAPSED_STRING'
        ){
            $this->tokens->tokens[$key] = new Token(
                'T_VARIABLE',
                '$' . trim($this->tokens->next2->value, '\'"'),
                $this->tokens->current->line
            );
            $this->tokens->unsetTokens('next1','next2','next3');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Array equation via 'Array' word
     * $arr = array();
     *
     * @param int     $key
     *
     * @return false Always returns false, because it doesn't unset any elements
     */
    public function updateArray_equation($key)
    {
        // Check the sequence for array equation
        if( ! $this->tokens->checkSequenceFromPosition($key + 1, $this->sequences['array_equation_array']) ){
            return false;
        }
            
        // Get end of array equation
        $variable_end = $this->tokens->searchForward($key, ';') - 1;
        if( ! $variable_end ){
            return false;
        }
        
        // Get all tokens of the array
        $array_tokens = $this->tokens->getRange($key + 4, $variable_end - 1);
        if( ! $array_tokens ){
            return false;
        }
        
        for(
            $i = 0;
            $arr_key = null, $arr_value = null, isset( $array_tokens[ $i ]);
            $arr_key = null, $arr_value = null, $i++
        ){
            
            // Case: [ 'a' => 'b' ] or [ 1 => 'b' ]
            if(
                isset($array_tokens[ $i + 1 ]) && $array_tokens[ $i + 1 ]->type === 'T_DOUBLE_ARROW' &&
                $array_tokens[ $i ]->isTypeOf( 'array_allowed_keys')
            ){
                $arr_key   = trim($array_tokens[ $i ]->value, '\'"');
                $arr_value = $array_tokens[ $i + 2 ];
                $i += 2; // Skip
                
            // Case: [ 'a', 'b', 'c' ]
            }elseif( $array_tokens[ $i ]->isTypeOf( 'array_allowed_values' ) ){
                $arr_key   = isset($this->arrays[ $this->tokens->current->value ])
                    ? count( $this->arrays[ $this->tokens->current->value ])
                    : 0;
                $arr_value = $array_tokens[ $i ];
            }
            
            if( $arr_key && $arr_value ){
                $array[ $arr_key ] = $arr_value;
            }
        }
        
        if( isset($array) ){
            $this->arrays[ $this->tokens->current->value ] = $array;
        }
        
        return false;
    }
    
    /**
     * Array equation via '[]' operator
     * $arr = [];
     *
     * @param int     $key
     *
     * @return false returns false if current token( $tokens[ $key ] ) was unset or true if isn't
     */
    public function updateArray_equationShort($key)
    {
        if( ! $this->tokens->checkSequenceFromPosition($key + 1, $this->sequences['array_equation_square_brackets']) ){
            return false;
        }
        
        $variable_end = $this->tokens->searchForward($key, ';') - 1;
        if( ! $variable_end ){
            return false;
        }
        
        // Get all tokens of the array
        $array_tokens = $this->tokens->getRange($key + 3, $variable_end - 1);
        if( ! $array_tokens ){
            return false;
        }
        
        for(
            $i = 0;
            $arr_key = null, $arr_value = null, isset( $array_tokens[ $i ]);
            $arr_key = null, $arr_value = null, $i++
        ){
            // Case: [ 'a' => 'b' ] or [ 1 => 'b' ]
            if(
                isset($array_tokens[ $i + 1 ]) && $array_tokens[ $i + 1 ]->type === 'T_DOUBLE_ARROW' &&
                $array_tokens[ $i ]->isTypeOf( 'array_allowed_keys')
            ){
                $arr_key   = trim($array_tokens[ $i ]->value, '\'"');
                $arr_value = $array_tokens[ $i + 2 ];
                $i += 2; // Skip
                
            // Case: [ 'a', 'b', 'c' ]
            }elseif( $array_tokens[ $i ]->isTypeOf( 'array_allowed_values' ) ){
                $arr_key   = isset($this->arrays[ $this->tokens->current->value ])
                    ? count( $this->arrays[ $this->tokens->current->value ])
                    : 0;
                $arr_value = $array_tokens[ $i ];
            }
            
            if( $arr_key && $arr_value ){
                $array[ $arr_key ] = $arr_value;
            }
        }
        
         if( isset($array) ){
            $this->arrays[ $this->tokens->current->value ] = $array;
        }
        
        return false;
    }
    
    /**
     * Array. New element equation via
     * $arr[] = 'value';
     *
     * @param int $key
     *
     * @return false returns false if current token( $tokens[ $key ] ) was unset or true if isn't
     */
    public function updateArray_newElement($key)
    {
        if(
            $this->tokens->next1->value === '[' &&
            $this->tokens->next2->value === ']' &&
            $this->tokens->next3->value === '='
        ){
            $var_temp = $this->tokens->getRange(
                $key + 4,
                $this->tokens->searchForward($key, ';') - 1
            );
            
            if( $var_temp !== false && count( $var_temp ) ){
                $var_temp = $var_temp[0];
                if( $var_temp->isTypeOf( 'array_allowed_values') ){
                    $this->arrays[ $this->tokens->current->value ][] = array(
                        $var_temp[0],
                        $var_temp[1],
                        $var_temp[2],
                    );
                }
            }
        }
        
        return false;
    }
    
    /**
     * Simple equation
     * $a = 'value';
     *
     * @param int $key
     *
     * @return false returns false if current token( $tokens[ $key ] ) was unset or true if isn't
     */
    public function updateVariables_equation($key)
    {
        // Simple equation
        // $a = 'value';
        if(
            $this->tokens->current->type  === 'T_VARIABLE' &&
            $this->tokens->next1  ->value === '='
        ){
            $variable_end = $this->tokens->searchForward($key, ';') - 1;
            if($variable_end){
                
                $variable_tokens = $this->tokens->getRange($key + 2, $variable_end);
                
                if(
                    count($variable_tokens) === 3 &&
                    $variable_tokens[0]->value === '"' &&
                    $variable_tokens[1]->type === 'T_ENCAPSED_AND_WHITESPACE' &&
                    $variable_tokens[2]->value === '"'
                ){
                    $variable_tokens = array( new Token(
                        'T_CONSTANT_ENCAPSED_STRING',
                        '\'' . $variable_tokens[1]->value . '\'',
                        $variable_tokens[1]->line
                    ) );
                }
                
                // Variable in a single quotes like $a = 'value';
            
                $this->variables[ $this->tokens->current->value ] = $variable_tokens;
                //var_dump( $this->variables);
            }
        }
        
        return false;
    }
    
    /**
     * Equation with concatenation. $a .= 'value';
     * Adding right expression to the appropriate variable
     *
     * @param int $key
     *
     * @return false always return false
     */
    public function updateVariables_equationWithConcatenation($key)
    {
        if(
            $this->tokens->current->type === 'T_VARIABLE' &&
            $this->tokens->next1  ->type === 'T_CONCAT_EQUAL'
        ){
            
            $tokens_of_variable = $this->tokens->getRange(
                $key + 2,
                $this->tokens->searchForward($key, ';') - 1
            );
            
            if( $tokens_of_variable ){
                
                // Variable in a double quotes like $a .= "$b";
                // We don't touch variables in a single quotes like $a .= 'value';
                if(
                    count( $tokens_of_variable ) === 3 &&
                    $tokens_of_variable[0]->value === '"' &&
                    $tokens_of_variable[1]->type  === 'T_ENCAPSED_AND_WHITESPACE' &&
                    $tokens_of_variable[2]->value === '"'
                ){
                    $tokens_of_variable = array(
                        new Token(
                            'T_CONSTANT_ENCAPSED_STRING',
                            '\'' . $tokens_of_variable[1]->value . '\'',
                            $tokens_of_variable[1]->line
                        ),
                    );
                }
                
                // If the variable exists
                if( isset( $this->variables[ $this->tokens->current->value ] ) ){
                    $this->variables[ $this->tokens->current->value ]->append($tokens_of_variable);
                }else{
                    $this->variables[ $this->tokens->current->value ] = ExtendedSplFixedArray::fromArray($tokens_of_variable);
                }
            }
        }
        
        return false;
    }
    
    /**
     * Search and remember constants definition
     * define('CONSTANT_NAME','CONSTANT_VALUE'
     *
     * @param int $key
     *
     * @return false returns false if current token( $tokens[ $key ] ) was unset or true if isn't
     */
    public function updateConstants($key)
    {
        if(
            $this->tokens->current->value === 'define' &&
            $this->tokens->checkSequenceFromPosition($key, $this->sequences['define_constant'] )
        ){
            $constant_name = trim( $this->tokens->next2->value, '\'"' );
            $this->constants[ $constant_name ] = trim( $this->tokens->next4->value, '\'"' );
        }
        
        return false;
    }
    
    /**
     * Concatenate variable in $this->variables
     *
     * @return void
     */
    public function concatenate(){
        
        foreach($this->variables as $var_name => $var){
            for($i = count($var)-1; $i > 0; $i--){
                $curr = isset($var[$i])   ? $var[$i]   : null;
                $next = isset($var[$i-1]) ? $var[$i-1] : null;
                if(
                    in_array( $curr[0], $this->variables_types_to_concat, true ) &&
                    in_array( $next[0], $this->variables_types_to_concat, true )
                ){
                    Controller::_concatenate($this->variables[$var_name], $i, true);
                }
            }
        }
    }
    
    /**
     * Replace variables with it's content
     *
     * @param int $key
     *
     * @return void
     */
    public function replace($key)
    {
            // Replace variable
            if( $this->tokens->current->type === 'T_VARIABLE' ){
                
                $variable_name = $this->tokens->current->value;
                
                // Arrays
                if( $this->isTokenInArrays($this->tokens->current) ){
                    
                    // Array element
                    if(
                        $this->tokens->next1->value === '[' &&
                        $this->tokens->next1->type === 'T_LNUMBER' &&
                        $this->tokens->next3->isValueIn( [ '.', '(', ';' ] )
                    ){
                        if( isset($this->arrays[ $variable_name ][ $this->tokens->next1->value[1] ][1]) ){
                            if( $this->tokens->next3->value === '(' ){
                                $this->tokens->tokens[$key] = new Token(
                                    'T_STRING',
                                    substr($this->arrays[ $variable_name ][ $this->tokens->next1->value[1] ][1], 1, -1),
                                    $this->tokens->current->line
                                );
                            }elseif( $this->tokens->next3->value === '.' ){
                                $this->tokens->tokens[$key] = new Token(
                                    'T_CONSTANT_ENCAPSED_STRING',
                                    '\'' . $this->arrays[ $variable_name ][ $this->tokens->next1->value[1] ][1] . '\'',
                                    $this->tokens->current->line
                                );
                            }else{
                                $this->tokens->tokens[$key] = new Token(
                                    $this->arrays[ $variable_name ][ $this->tokens->next1->value[1] ][0],
                                    '\'' . $this->arrays[ $variable_name ][ $this->tokens->next1->value[1] ][1] . '\'',
                                    $this->tokens->current->line
                                );
                            }
                            
                            $this->tokens->unsetTokens('next1', 'next2', 'next3');
                        }
                    }
                    
                // Variables
                }elseif(
                    $this->isTokenInVariables($this->tokens->current) &&
                    count($this->variables[ $variable_name ]) === 1 &&
                    in_array($this->variables[ $variable_name ][0][0], $this->variables_types_to_concat, true)
                ){
                    // Array or symbol from string replacement
                    if(
                        $this->tokens->next2->type === 'T_LNUMBER' &&
                        $this->tokens->next1->isValueIn( [ '[', '{' ] )
                    ){
                        if( isset(
                            $this->variables[ $variable_name ][0][1][ $this->tokens->next2->value ],
                            $this->variables[ $variable_name ][0][1][ $this->tokens->next2->value + 1]
                        ) ){
                            $this->tokens->tokens[$key] = new Token(
                                'T_CONSTANT_ENCAPSED_STRING',
                                '\'' . $this->variables[ $variable_name ][0][1][ $this->tokens->next2->value + 1] . '\'',
                                $this->tokens->current->line
                            );
                            $this->tokens->unsetTokens('next1', 'next2', 'next3');
                        }
                        
                    // @todo Learn to replace $$var to $var_value
                    // }elseif( is_array( $next ) && $next === 'T_VARIABLE' ){
                    
                    // Single variable replacement
                    }else{
                        
                        // Variables function
                        if( $this->tokens->next1->value === '(' ){
                            $this->tokens->tokens[ $key ] = new Token(
                                'T_STRING',
                                substr($this->variables[ $variable_name ][0][1], 1, -1),
                                $this->tokens->current->line
                            );
                            // Variables in double/single quotes
                        }elseif( ! $this->tokens->next1->isTypeOf('equation') ){
                            $this->tokens->tokens[ $key ] = new Token(
                                ! $this->tokens->prev1->value === '"' ? 'T_CONSTANT_ENCAPSED_STRING' : 'T_ENCAPSED_AND_WHITESPACE',
                                ! $this->tokens->prev1->value === '"' ? $this->variables[ $variable_name ][0][1] : substr($this->variables[ $variable_name ][0][1],1,-1),
                                $this->tokens->current->line
                            );
                        }
                    }
                }
                
            // Constant replacement
            // @todo except cases when name of constant equal to something. Check type and siblings tokens
            }elseif( $this->isTokenInConstants($this->tokens->current) ){
                $this->tokens->tokens[$key] = new Token(
                    'T_CONSTANT_ENCAPSED_STRING',
                    '\'' . $this->constants[ $this->tokens->current->value ] . '\'',
                    $this->tokens->current->line
                );
            }
    }
    
    /**
     * Add variables with user input to BAD list
     */
    public function detectBad()
    {
        do{
            $bad_vars_count = count($this->variables_bad);
            
            foreach( $this->variables as $var_name => $variable ){
                foreach( $variable as $var_part ){
                    if(
                        $var_part[0] === 'T_VARIABLE' &&
                        (
                            in_array($var_part[1], $this->variables_bad_default, true) ||
                            isset($this->variables_bad[$var_part[1]])
                        )
                    ){
                        $this->variables_bad[$var_name] = $variable;
                        continue(2);
                    }
                }
            }
        }while( $bad_vars_count !== count($this->variables_bad) );
    }
    
    /**
     * Check the set of tokens for bad variables
     *
     * @param $tokens
     *
     * @return bool
     */
    public function isSetOfTokensHasBadVariables( &$tokens )
    {
    	foreach( $tokens as $token ){
    		if(
    			$token[0] === 'T_VARIABLE' &&
			    in_array($token[0], $this->variables_bad_default, true )
			    //in_array($token[0], $this->variables_bad, true )  // @todo Make bad variables gathering
		    ){
    			return true;
		    }
	    }
    	
    	return false;
    }
    
    /**
     * Check if the given token in arrays
     *
     * @param $token
     *
     * @return bool
     */
    public function isTokenInArrays($token)
    {
        return $token->type === 'T_VARIABLE' && isset($this->arrays[ $token->value ]);
    }
    
    /**
     * Check if the given token in arrays
     *
     * @param $token
     *
     * @return bool
     */
    public function isTokenInVariables($token)
    {
        return $token->type === 'T_VARIABLE' && isset($this->variables[ $token->value ]);
    }
    
    /**
     * Check if the given token in arrays
     *
     * @param $token
     *
     * @return bool
     */
    public function isTokenInConstants($token)
    {
        return $token->type === 'T_STRING' && isset($this->constants[ $token->value ]);
    }
}