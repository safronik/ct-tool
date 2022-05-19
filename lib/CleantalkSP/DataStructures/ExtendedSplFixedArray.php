<?php


namespace CleantalkSP\DataStructures;

/**
 * Class ExtendedSplFixedArray
 *
 * Extends \SplFixedArray for purposes of heuristic scanner
 *
 * @version 1.0.0
 * @since   2.85
 */
class ExtendedSplFixedArray extends \SplFixedArray
{
	/**
     * Creates new \SplFixedArray from given \Iterable and \Countable object
     *
	 * @param array $array
	 * @param bool  $save_indexes
	 *
	 * @return ExtendedSplFixedArray
	 * @psalm-suppress MixedAssignment
	 */
	public static function createFromArray(array $array, $save_indexes = true)
	{
        $self = new self(count($array));
        if($save_indexes) {
            foreach($array as $key => $value) {
                $self[(int) $key] = $value;
            }
        } else {
            $i = 0;
            foreach (array_values($array) as $value) {
                $self[$i] = $value;
                $i++;
            }
        }
        
        return $self;
    }
	
	/**
     * Implementation of native PHP array_column for \SplFixedArray
     *
	 * @param int|string $column_to_get
	 *
	 * @return array|false
	 */
    public function getColumn($column_to_get)
	{
		$out = array();
		
        foreach( $this as $element ){
			if( isset( $element[ $column_to_get ]) ){
				$out[] = $element[ $column_to_get ];
			}
		}
		
		return $out;
	}
	
    /**
     * Reduce \SplFixedArray by recalculating size and reindex whole \SplFixedArray
     *
     * @param \SplFixedArray $splFixedArray
     * @param int            $from_key
     *
     * @return bool
     */
	public static function reindex( $splFixedArray, $from_key = 0 )
	{
		for(
		    $max_key = count($splFixedArray),
		    $new_key = $from_key,
            $old_key = $from_key;
		
		    $old_key < $max_key;
		
		    $old_key++
        ){
            // Skip null and nonexistent values
            if( ! isset($splFixedArray[ $old_key ]) ){
                $unseted[$old_key] = $splFixedArray[ $old_key ];
                continue;
            }
            
            // Reindex
            $splFixedArray[ $new_key ] = $splFixedArray[ $old_key ];
            $new_key++;
        }
        
		// Set new size of \SplFixedArray
		$splFixedArray->setSize( $from_key + $new_key );
		
		return true;
	}
    
    /**
     * Get slice from the current ExtendedSplFixedArray
     * Return only not empty values
     *
     * @param int  $start    Start key
     * @param int  $end      End key
     * @param bool $clean_up Should we clean from null values?
     *
     * @return ExtendedSplFixedArray|false
     */
	public function slice( $start, $end, $clean_up = true ){
		
	    
	    if( $start === false || $end === false ){
	        return false;
        }
	    
	    $out = [];
	    
		for(
			$index = $start;
			$index <= $end;
			$index++
		){
            // Return only not empty values
            if( isset($this[ $index ]) ){
                $out[] = $this[ $index ];
            
            // If value is empty extend range
            }else{
                $index++;
            }
		}
		
        $out = self::createFromArray( $out );
        
		// Clean up the null values if needed
		if( $clean_up ){
		    self::reindex( $out );
        }
		
		return $out;
	}
    
    /**
     * Implementation of array_unshift for \SplFixedArray
     *
     * @param $first_elem
     *
     * @return ExtendedSplFixedArray
     */
    public function unshift($first_elem)
    {
        $temp_arr = $this->toArray();
        array_unshift($temp_arr, $first_elem);
        
        return self::createFromArray($temp_arr);
    }
    
    public function append($appendixes)
    {
        $current_size = count($this);
        $this->setSize( $current_size + count($appendixes) ); // Set a new size
        foreach( $appendixes as $appendix ){
            $this[ ++$current_size - 1 ] = $appendix;
        }
    }
}