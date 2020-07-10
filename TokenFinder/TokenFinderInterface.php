<?php


namespace Ling\TokenFun\TokenFinder;

/**
 * TokenFinderInterface
 *
 */
interface TokenFinderInterface
{


    /**
     * @return array of match
     *                  every match is an array with the following entries:
     *                          0: int startIndex
     *                                      the index at which the pattern starts
     *                          1: int endIndex
     *                                      the index at which the pattern ends
     *                          ...: extra numbers can be added, depending on the concrete class
     *
     */
    public function find(array $tokens);
}
