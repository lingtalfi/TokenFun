<?php


namespace Ling\TokenFun\TokenFinder\Tool;

use Ling\Bat\ClassTool;
use Ling\Bat\FileSystemTool;
use Ling\DirScanner\DirScanner;
use Ling\TokenFun\TokenArrayIterator\TokenArrayIterator;
use Ling\TokenFun\TokenArrayIterator\Tool\TokenArrayIteratorTool;
use Ling\TokenFun\TokenFinder\ClassNameTokenFinder;
use Ling\TokenFun\TokenFinder\ClassOpeningBracketTokenFinder;
use Ling\TokenFun\TokenFinder\ClassPropertyTokenFinder;
use Ling\TokenFun\TokenFinder\InterfaceTokenFinder;
use Ling\TokenFun\TokenFinder\MethodTokenFinder;
use Ling\TokenFun\TokenFinder\NamespaceTokenFinder;
use Ling\TokenFun\TokenFinder\ParentClassNameTokenFinder;
use Ling\TokenFun\TokenFinder\UseStatementsTokenFinder;
use Ling\TokenFun\Tool\TokenTool;


/**
 * TokenFinderTool
 * @author Lingtalfi
 * 2016-01-02 -> 2020-07-21
 *
 */
class TokenFinderTool
{


    /**
     * @param array $matches , an array of matches as returned by a TokenFinder object.
     */
    public static function matchesToString(array &$matches, array $tokens)
    {
        foreach ($matches as $k => $v) {
            list($start, $end) = $v;
            $s = '';
            for ($i = $start; $i <= $end; $i++) {
                $tok = $tokens[$i];
                if (is_string($tok)) {
                    $s .= $tok;
                } elseif (is_array($tok)) {
                    $s .= $tok[1];
                }
            }
            $matches[$k] = $s;
        }
    }


    /**
     *
     * Options:
     * - includeInterfaces: bool=false, whether to include interfaces
     *
     *
     * @return array of class names found, prefixed with namespace if $withNamespaces=true
     *
     */
    public static function getClassNames(array $tokens, $withNamespaces = true, array $options = [])
    {
        $ret = [];
        $o = new ClassNameTokenFinder();
        $includeInterface = $options['includeInterfaces'] ?? false;
        $o->setIncludeInterface($includeInterface);

        $matches = $o->find($tokens);
        if ($matches) {
            $ret = $matches;
            TokenFinderTool::matchesToString($ret, $tokens);
            if (true === $withNamespaces && false !== $namespace = self::getNamespace($tokens)) {
                array_walk($ret, function (&$v) use ($namespace) {
                    $v = $namespace . '\\' . $v;
                });
            }
        }
        return $ret;
    }


    /**
     * Returns an array of basic information for every class properties of the given class.
     * The variable names are used as indexes.
     *
     * Note: the given class must be reachable by the auto-loaders.
     *
     *
     *
     * Each basic info array contains the following:
     *
     * - varName: string, the variable name
     * - hasDocComment: bool, whether this property has a docblock comment attached to it (it's a comment that starts with slash double asterisk)
     * - doComment: string, the docblock comment if any (an empty string otherwise)
     * - isPublic: bool, whether the property's visibility is public
     * - isProtected: bool, whether the property's visibility is protected
     * - isPrivate: bool, whether the property's visibility is private
     * - isStatic: bool, whether the property is declared as static
     * - content: string, the whole property declaration, as written in the file, including the multi-line comments if any
     * - startLine: int, the line number at which the property "block" (i.e. including the doc block comment if any) starts
     * - endLine: int, the line number at which the property "block" ends
     * - commentStartLine: int, the line number at which the doc bloc comment starts, or false if there is no block comment
     * - commentEndLine: int, the line number at which the doc bloc comment ends, or false if there is no block comment
     *
     *
     * @param string $className
     * @return array
     */
    public static function getClassPropertyBasicInfo(string $className): array
    {
        $ret = [];
        $file = ClassTool::getFile($className);
        $tokens = token_get_all(file_get_contents($file));
        $o = new ClassPropertyTokenFinder();
        $matches = $o->find($tokens);
        foreach ($matches as $match) {
            list($startIndex, $endIndex) = $match;
            $slice = TokenTool::slice($tokens, $startIndex, $endIndex);

            $hasComment = TokenTool::matchAny([
                T_DOC_COMMENT,
            ], $slice);

            $isPublic = TokenTool::matchAny([
                T_PUBLIC,
            ], $slice);
            $isProtected = TokenTool::matchAny([
                T_PROTECTED,
            ], $slice);
            $isPrivate = TokenTool::matchAny([
                T_PRIVATE,
            ], $slice);
            $isStatic = TokenTool::matchAny([
                T_STATIC,
            ], $slice);

//            az(TokenTool::explicitTokenNames($slice));
            $varToken = TokenTool::fetch($slice, [T_VARIABLE]);
            $varName = substr($varToken[1], 1); // removing the dollar symbol

            $commentToken = TokenTool::fetch($slice, [T_DOC_COMMENT]);
            $docComment = '';
            $docCommentStartLine = false;
            $docCommentEndLine = false;
            if (false !== $commentToken) {
                $docComment = $commentToken[1];
                $docCommentStartLine = $commentToken[2];
                $p = explode(PHP_EOL, $docComment);
                $nbLines = count($p);
                $docCommentEndLine = $docCommentStartLine + $nbLines - 1;


            }


            list($startLine, $endLine) = TokenTool::getStartEndLineByTokens($slice);
            $content = TokenTool::tokensToString($slice);
            $ret[$varName] = [
                "varName" => $varName,
                "hasDocComment" => $hasComment,
                "docComment" => $docComment,
                "isPublic" => $isPublic,
                "isProtected" => $isProtected,
                "isPrivate" => $isPrivate,
                "isStatic" => $isStatic,
                "content" => $content,
                "startLine" => $startLine,
                "endLine" => $endLine,
                "commentStartLine" => $docCommentStartLine,
                "commentEndLine" => $docCommentEndLine,
            ];
        }
        return $ret;
    }


    /**
     * @return string|false, the classname of the parent class if any,
     * and include the full name if $fullName is set to true.
     *
     * When fullName is true, it tries to see if there is a use statement matching
     * the parent class name, and returns it if it exists.
     * Otherwise, it just prepends the namespace (if no use statement matched the parent class name).
     *
     * Note: as for now it doesn't take into account the "as" alias (i.e. use My\Class as Something)
     *
     */
    public static function getParentClassName(array $tokens, $fullName = true)
    {
        $o = new ParentClassNameTokenFinder();
        $matches = $o->find($tokens);
        if ($matches) {
            $ret = $matches;
            TokenFinderTool::matchesToString($ret, $tokens);

            // there can only be one parent class in php
            $ret = array_shift($ret);

            if (true === $fullName) {

                $useStmts = self::getUseDependencies($tokens);
                foreach ($useStmts as $dep) {
                    $p = explode('\\', $dep);
                    $lastName = array_pop($p);
                    if ($lastName === $ret) {
                        return $dep;
                    }
                }


                if (false !== ($namespace = self::getNamespace($tokens))) {
                    $ret = $namespace . '\\' . ltrim($ret, '\\');
                }
            }

            return $ret;
        } else {
            return false;
        }
    }


    /**
     * @return array, the names of the implemented interfaces (search for the "CCC implements XXX" expression) if any,
     * and include the full name if $fullName is set to true.
     *
     *
     *
     *
     * When fullName is true, it tries to see if there is a use statement matching
     * the interface class name, and returns it if it exists.
     * Otherwise, it just prepends the namespace (if no use statement matched the interface class name).
     *
     * Note: as for now it doesn't take into account the "as" alias (i.e. use My\Class as Something)
     *
     */
    public static function getInterfaces(array $tokens, $fullName = true)
    {
        $o = new InterfaceTokenFinder();
        $matches = $o->find($tokens);
        $ret = [];
        if ($matches) {
            $string = $matches;
            TokenFinderTool::matchesToString($string, $tokens);

            // expect only one string to be returned
            $string = array_shift($string);

            $ret = explode(',', $string);
            $ret = array_map('trim', $ret);


            if (true === $fullName) {

                $useStmts = self::getUseDependencies($tokens);
                foreach ($ret as $k => $className) {
                    $found = false;
                    foreach ($useStmts as $dep) {
                        $p = explode('\\', $dep);
                        $lastName = array_pop($p);
                        if ($lastName === $className) {
                            $ret[$k] = $dep;
                            $found = true;
                        }
                    }

                    if (false === $found) {
                        if (false !== ($namespace = self::getNamespace($tokens))) {
                            $ret[$k] = $namespace . '\\' . $className;
                        }
                    }
                }
            }
        }
        return $ret;
    }


    /**
     *
     * @return array of <info>, each info is an array with the following properties:
     *      - name: string
     *      - visibility: public (default)|private|protected
     *      - abstract: bool
     *      - final: bool
     *      - methodStartLine: int
     *      - methodEndLine: int
     *      - content: string
     *      - args: string
     *
     *      - commentType: null|regular\docBlock
     *      - commentStartLine: null|int
     *      - commentEndLine: null|int
     *      - comment: null|string
     *
     *      - startIndex: int, the index at which the pattern starts
     */
    public static function getMethodsInfo(array $tokens)
    {

        $ret = [];
        $o = new MethodTokenFinder();
        $matches = $o->find($tokens);


        if ($matches) {

//            az($matches);

            foreach ($matches as $match) {


                $length = $match[1] - $match[0];
                $matchTokens = array_slice($tokens, $match[0], $length);


//                if (299 === $match[0]) {
//                    az($match, TokenTool::explicitTokenNames($matchTokens));
//                }


                $comment = null;
                $commentType = null;
                $commentStartLine = null;
                $methodStartLine = null;
                $methodEndLine = null;
                $visibility = 'public';
                $abstract = false;
                $final = false;
                $name = null;
                $args = '';
                $content = '';
                $argsStarted = false;
                $contentStarted = false;
                $nameFound = false;


                $tai = new TokenArrayIterator($matchTokens);

                while ($tai->valid()) {
                    $token = $tai->current();
                    if (false === $nameFound) {
                        if (true === TokenTool::match([T_COMMENT, T_DOC_COMMENT], $token)) {
                            if (true === TokenTool::match(T_COMMENT, $token)) {
                                $commentType = 'regular';
                            } else {
                                $commentType = 'docBlock';
                            }
                            $comment = $token[1];
                            $commentStartLine = $token[2];
                        }

                        if (true === TokenTool::match([T_PUBLIC, T_PROTECTED, T_PRIVATE], $token)) {
                            $visibility = $token[1];
                            $methodStartLine = $token[2];
                        }

                        if (true === TokenTool::match(T_ABSTRACT, $token)) {
                            $abstract = true;
                            if (null === $methodStartLine) {
                                $methodStartLine = $token[2];
                            }
                        }

                        if (true === TokenTool::match(T_FINAL, $token)) {
                            $final = true;
                            if (null === $methodStartLine) {
                                $methodStartLine = $token[2];
                            }
                        }


                        if (true === TokenTool::match(T_FUNCTION, $token)) {

                            if (null === $methodStartLine) {
                                $methodStartLine = $token[2];
                            }

                            $tai->next();
                            TokenArrayIteratorTool::skipWhiteSpaces($tai);
                            $name = $tai->current()[1];
                            $nameFound = true;
                            $tai->next();
                            TokenArrayIteratorTool::skipWhiteSpaces($tai);
                        }
                    }

                    if (false === $argsStarted && true === TokenTool::match('(', $tai->current())) {
                        $argsTokens = [];
                        TokenArrayIteratorTool::moveToCorrespondingEnd($tai, null, $argsTokens);
                        $args = TokenTool::tokensToString($argsTokens);
                        $argsStarted = true;
                    }
                    if (false === $contentStarted && true === TokenTool::match('{', $tai->current())) {
                        $contentTokens = [];
                        TokenArrayIteratorTool::moveToCorrespondingEnd($tai, null, $contentTokens);

                        $content = TokenTool::tokensToString($contentTokens);
                        $contentStarted = true;
                    }
                    $tai->next();
                }


                $p = explode(PHP_EOL, $content);
                $methodEndLine = $methodStartLine + count($p);


                if (null !== $commentStartLine) {
                    if ('//' === substr(trim($comment), 0, 2)) {
                        $commentEndLine = $commentStartLine;
                    } else {
                        $p = explode(PHP_EOL, $comment);
                        $commentEndLine = $commentStartLine + count($p) - 1;
                    }


                }


                $ret[] = [
                    'name' => $name,
                    'visibility' => $visibility,
                    'abstract' => $abstract,
                    'final' => $final,
                    'methodStartLine' => $methodStartLine,
                    'methodEndLine' => $methodEndLine,
                    'content' => $content,
                    'args' => $args,

                    'commentType' => $commentType,
                    'commentStartLine' => $commentStartLine,
                    'commentEndLine' => $commentEndLine,
                    'comment' => $comment,
                    'startIndex' => $match[0],
                ];
            }
        }
        return $ret;
    }

    /**
     * @param array $tokens
     * @return false|string, the first namespace found or false if there is no namespace
     */
    public static function getNamespace(array $tokens)
    {

        $ret = false;
        $o = new NamespaceTokenFinder();
        $matches = $o->find($tokens);
        if ($matches) {
            TokenFinderTool::matchesToString($matches, $tokens);
            $f = $matches[0];
            $ret = trim(substr($f, 10, -1));
        }

        return $ret;
    }


    /**
     * @return array of use statements' class names
     */
    public static function getUseDependencies(array $tokens, $sort = true)
    {
        $ret = [];
        $o = new UseStatementsTokenFinder();
        $matches = $o->find($tokens);
        if ($matches) {
            $ret = $matches;
            TokenFinderTool::matchesToString($ret, $tokens);
            array_walk($ret, function (&$v) {
                $p = explode(' ', $v);
                $v = rtrim($p[1], ';');
            });

        }
        if (true === $sort) {
            sort($ret);
        }
        return $ret;
    }

    /**
     * @return array of use statements' class names inside the given directory
     */
    public static function getUseDependenciesByFolder($dir)
    {
        $ret = [];
        DirScanner::create()->scanDir($dir, function ($path, $rPath, $level) use (&$ret) {
            if (is_file($path) && 'php' === strtolower(FileSystemTool::getFileExtension($path))) {
                $tokens = token_get_all(file_get_contents($path));
                $ret = array_merge($ret, TokenFinderTool::getUseDependencies($tokens));
            }
        });
        $ret = array_unique($ret);
        sort($ret);
        return $ret;
    }


    /**
     * Returns an array of all the use statements used by the given reflection classes.
     *
     * @param \ReflectionClass[] $reflectionClasses
     * @return array
     */
    public static function getUseDependenciesByReflectionClasses(array $reflectionClasses)
    {
        $ret = [];
        foreach ($reflectionClasses as $class) {
            $tokens = token_get_all(file_get_contents($class->getFileName()));
            $ret = array_merge($ret, TokenFinderTool::getUseDependencies($tokens));
        }
        $ret = array_unique($ret);
        sort($ret);
        return $ret;
    }
}
