<?php
/**
 * This file is a part of SebkSmallOrmCore
 * Copyright 2021 - Sébastien Kus
 * Under GNU GPL V3 licence
 */

namespace Sebk\SmallOrmCore\Generator;

/**
 * Class FileParser
 * @package Sebk\SmallOrmCore\Generator
 */
class FileParser
{
    public static $visibilities = ["public", "private", "protected"];
    protected $content;

    /**
     * FileParser constructor.
     * @param $filepath
     */
    public function __construct($content)
    {
        // read file
        $this->content = $content;
    }

    /**
     * Explode string as array
     * Keys of array is the position of parts
     * @param $delimiter
     * @param $string
     * @param $startPosition
     * @return array
     */
    private function explode($delimiter, $string, $startPosition)
    {
        $result = [];
        $word = "";
        $j = $startPosition;
        for($i = 0; $i < strlen($string); $i++) {
            if(substr($string, $i, 1) == $delimiter) {
                $result[$i - strlen($word)] = $word;
                $j = $i;
                $word = "";
            } else {
                $word = $word . substr($string, $i, 1);
            }

            $j++;
        }

        if($word != "") {
            $result[$j] = $word;
        }

        return $result;
    }

    /**
     * Split string in words (word delimiters is array of string)
     * Key of result array is the first position of the word in string
     * @param $delimiters
     * @param null $string
     * @param int $startPosition
     * @return array
     */
    public function getWordsAsArray($delimiters, $string = null, $startPosition = 0)
    {
        // by default use content of file
        if($string === null) {
            $string = $this->content;
        }

        // get first key
        reset($delimiters);
        $currentKey = key($delimiters);

        // get words for current delimiter
        $words = $this->explode($delimiters[$currentKey], $string, $startPosition);

        // delimiter is treated
        unset($delimiters[$currentKey]);

        // if last delimiter, simply return words
        if(count($delimiters) == 0) {
            return $words;
        }

        // foreach words, resplit for other delimiters
        $result = [];
        foreach ($words as $startPosition => $word) {
            $subWords = $this->getWordsAsArray($delimiters, $word, $startPosition);
            foreach($subWords as $subPosition => $subWord) {
                $result[$startPosition] = $subWord;
            }
        }

        // cleanup empty values
        foreach($result as $key => $word) {
            if($word == "") {
                unset($result[$key]);
            }
        }

        return $result;
    }

    /**
     * Find php function in content
     * @param $function
     * @param string $visibility
     * @return int|string
     * @throws \Exception
     */
    public function findFunctionDefinition($function, $visibility = "protected") {
        // test visibility coherence
        if(!in_array($visibility, static::$visibilities)) {
            throw new \Exception("Visibiliy must be [".implode(", ", static::$visibilities)."]");
        }

        // split content in array of words
        $words = $this->getWordsAsArray([" ", "\t", "\n", "\r", "(", ")", "{", "}"]);

        // words to find
        $findWords = [$visibility, "function", $function];

        // start at first step
        $findStep = 0;

        // position found of step 1
        $start = 0;

        // not found by default
        $found = false;

        // foreach words
        foreach($words as $startWordPosition => $word) {
            // if current step found
            if($word == $findWords[$findStep]) {
                if($findStep == 0) {
                    // set the start position of word if first step
                    $start = $startWordPosition;
                } elseif($findStep == count($findWords) - 1) {
                    // if last step, then we found the target
                    $found = true;
                    break;
                }
                $findStep++;
            } else {
                // if not found, restart from first step
                $findStep = 0;
            }
        }

        if($found) {
            // if found, return position
            return $start;
        } else {
            // else return not found constant
            throw new \Exception("Function ".$function." not found");
        }
    }

    /**
     * Find start and end function positions
     * @param $function
     * @param string $visibility
     * @return array
     * @throws \Exception
     */
    public function findFunctionPos($function, $visibility = "protected") {
        $start = $this->findFunctionDefinition($function, $visibility);
        $end = -1;

        $braceCount = 0;
        $foundOpeningBrace = false;
        for($i = $start; $i < strlen($this->content); $i++) {
            if(substr($this->content, $i, 1) == "{") {
                $braceCount++;
                $foundOpeningBrace = true;
            }

            if(substr($this->content, $i, 1) == "}") {
                $braceCount--;
            }

            if($foundOpeningBrace && $braceCount == 0) {
                $end = $i + 1;
                break;
            }
        }

        if($end != -1) {
            return array("start" => $start, "end" => $end);
        }

        throw new \Exception("End of function ".$function." not found");
    }
}