<?php

namespace Ling\TokenFun\Tool;

use Ling\TokenFun\Exception\TokenFunException;
use Ling\TokenFun\TokenArrayIterator\TokenArrayIterator;


/**
 * TokenTool
 * @author Lingtalfi
 * 2016-01-02 -> 2020-07-10
 *
 *
 *
 *
 *
 * tokenProp
 * ------------------
 * A token prop is used as a tool to compare against a php token.
 * It's either:
 *      - a string, in which case it matches with a php token of type string
 *      - an int, in which case it matches with the php token type.
 *      - an array, which contains an arbitrary number of other elements (of type string or int).
 *                      If one at least of those elements matches against the php token,
 *                      the the token prop matches.
 *
 *
 *
 *
 */
class TokenTool
{


    public static function explicitTokenNames(array $tokens)
    {

        $ret = [];
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $ret[] = [
                    token_name($token[0]),
                    $token[1],
                    $token[2],
                ];
            } else {
                $ret[] = $token;
            }
        }
        return $ret;
    }


    /**
     * @param $symbol ,
     *              a tokenProp to use as the delimiter.
     *              see TokenArrayIteratorTool for more on tokenProp.
     *
     * @param array $tokens
     * @param null|int $limit ,
     *                      null means no limit
     * @return array
     */
    public static function explodeTokens($symbol, array $tokens, $limit = null)
    {
        $ret = [];
        $tai = new TokenArrayIterator($tokens);
        $eat = [];
        while ($tai->valid()) {
            $cur = $tai->current();
            if (TokenTool::match($symbol, $cur)) {
                if (null === $limit || $limit > count($ret)) {
                    $ret[] = $eat;
                    $eat = [];
                }
            } else {
                $eat[] = $cur;
            }
            $tai->next();
        }
        if (null === $limit || $limit > count($ret)) {
            $ret[] = $eat;
        }
        return $ret;
    }


    /**
     * Returns the first token matching the given tokenProp definition, or false if none of them matches.
     *
     * For more info about tokenProp see the comments at the top of this class.
     *
     *
     * @param array $tokens
     * @param $tokenProp
     * @return mixed|false
     */
    public static function fetch(array $tokens, $tokenProp)
    {
        $ret = [];
        foreach ($tokens as $token) {
            if (true === self::match($token, $tokenProp)) {
                return $token;
            }
        }
        return false;
    }

    /**
     * Returns an array of all given tokens matching the given tokenProp definition.
     * For more info about tokenProp see the comments at the top of this class.
     *
     *
     * @param array $tokens
     * @param $tokenProp
     * @return array
     */
    public static function fetchAll(array $tokens, $tokenProp): array
    {
        $ret = [];
        foreach ($tokens as $token) {
            if (true === self::match($token, $tokenProp)) {
                $ret[] = $token;
            }
        }
        return $ret;
    }


    /**
     * Returns an array: [startLine, endLine] containing the line numbers at which the given tokens start and end.
     *
     * If all the given tokens don't have this information (i.e. all tokens are special chars),
     * and exception is thrown.
     *
     *
     * @param array $tokens
     * @return array
     * @throws \Exception
     */
    public static function getStartEndLineByTokens(array $tokens): array
    {
        $start = null;
        $end = null;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (null === $start) {
                    $start = $token[2];
                    $end = $start;
                } else {
                    $end = $token[2];
                }
            }
        }
        if (null === $start) {
            throw new TokenFunException("No line number found in the given tokens.");
        }
        return [$start, $end];
    }


    /**
     * Strip whitespace (or other characters) from the beginning of a string.
     *
     * @param array $tokens
     * @param array $chars , an array of tokenProp (see TokenArrayIteratorTool).
     * @return array representing the trimmed tokens.
     */
    public static function ltrim(array $tokens, array $chars = null)
    {
        if (null === $chars) {
            $chars = [T_WHITESPACE];
        }
        $tai = new TokenArrayIterator($tokens);
        while ($tai->valid()) {
            if (TokenTool::match($chars, $tai->current())) {
                unset($tokens[$tai->key()]);
            } else {
                break;
            }
            $tai->next();
        }
        return array_merge($tokens);
    }

    /**
     * Returns whether the given token matches the given tokenProp.
     *
     * With:
     *
     * - token: the php token (an array or a string)
     * - tokenProp: see the definition at the top of this class
     *
     *
     * @return bool
     * @throws \Exception
     */
    public static function match($tokenProp, $token): bool
    {
        $ret = false;
        if (is_string($tokenProp)) {
            $ret = ($tokenProp === $token);
        } elseif (is_integer($tokenProp)) {
            $ret = (is_array($token) && $token[0] === $tokenProp);
        } elseif (is_array($tokenProp)) {
            foreach ($tokenProp as $tProp) {
                if (true === self::match($tProp, $token)) {
                    $ret = true;
                    break;
                }
            }
        } else {
            throw new \InvalidArgumentException("argument tokenProp must be either a string or an int");
        }
        return $ret;
    }


    /**
     * Returns whether any of the given tokens matches the given tokenProp.
     *
     * @param $tokenProp
     * @param array $tokens
     * @return bool
     */
    public static function matchAny($tokenProp, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (true === self::match($tokenProp, $token)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Strip whitespace (or other characters) from the end of a string.
     *
     * @param array $tokens
     * @param array $chars , an array of tokenProp (see TokenArrayIteratorTool).
     * @return array representing the trimmed tokens.
     */
    public static function rtrim(array $tokens, array $chars = null)
    {
        if (null === $chars) {
            $chars = [T_WHITESPACE];
        }
        if ($tokens) {
            $n = count($tokens) - 1;
            $tai = new TokenArrayIterator($tokens);
            $tai->seek($n);
            while ($tai->valid()) {
                if (TokenTool::match($chars, $tai->current())) {
                    unset($tokens[$tai->key()]);
                } else {
                    break;
                }
                $tai->prev();
            }
        }
        return array_merge($tokens);
    }


    /**
     * Returns the array slice of the given tokens, which starts and ends at the given indices.
     *
     *
     *
     * @param array $tokens
     * @param int $startIndex
     * @param int $endIndex
     * @return array
     * @throws TokenFunException
     */
    public static function slice(array $tokens, int $startIndex, int $endIndex): array
    {
        if ($endIndex < $startIndex) {
            throw new TokenFunException("endIndex ($endIndex) must be greater than startIndex ($startIndex).");
        }
        $endIndex++;
        return array_slice($tokens, $startIndex, $endIndex - $startIndex);
    }


    public static function tokensToString(array $tokens)
    {
        $s = '';
        foreach ($tokens as $token) {
            if (is_string($token)) {
                $s .= $token;
            } else {
                $s .= $token[1];
            }
        }
        return $s;
    }


    /**
     * Strip whitespace (or other characters) from the beginning and end of a string.
     *
     * @param array $tokens
     * @param array $chars , an array of tokenProp (see TokenArrayIteratorTool).
     * @return array representing the trimmed tokens.
     */
    public static function trim(array $tokens, array $chars = null)
    {
        $tokens = self::ltrim($tokens, $chars);
        $tokens = self::rtrim($tokens, $chars);
        return $tokens;
    }


}
