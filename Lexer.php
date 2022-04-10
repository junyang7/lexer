<?php


class Lexer
{


    private $origin_string = ''; // 原始输入字符串
    private $pattern_entity = '/\S/'; // 实体字符正则表达式
    private $pattern_escape = '/\s/'; // 空白字符正则表达式
    private $pattern_define = '/[,;:<>\(\)\[\]\{\}\+\-\*\/\?#\'"\\\]/'; // 特殊字符正则表达式
    private $string_entity = ''; // 实体字符字符串
    private $string_escape = ''; // 空白字符字符串
    private $split_line_list = ''; // 将原始输入字符串按|n切割并补足/n后的数组
    private $token_list = []; // 一维单词数组：单词
    private $is_string = FALSE; // 是否是字符串
    private $is_string_dc = FALSE; // 是否是字符串: <<<
    private $eof = ''; // <<<EOF
    private $is_string_sq = FALSE; // 是否是字符串: ''
    private $is_string_dq = FALSE; // 是否是字符串: ""
    private $is_comment = FALSE; // 是否是注释
    private $is_comment_fk = FALSE; // 是否是注释: //
    private $is_comment_ak = FALSE; // 是否是注释: /* */
    private $is_comment_hk = FALSE; // 是否是注释: #
    private $keyword_list = [
        '__halt_compiler',
        'abstract',
        'array',
        'and',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'finally',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
    ];
    private function _word_escape($i)
    {

        if(strlen($this->string_escape) > 0)
        {
            $token = [];
            $token['token_line'] = $i + 1;
            $token['token_type'] = 'escape';
            $token['token_text'] = $this->string_escape;
            $this->token_list[] = $token;
            $this->string_escape = '';
        }

    }
    private function _word_entity($i)
    {

        if(strlen($this->string_entity) > 0)
        {
            $token = [];
            $token['token_line'] = $i + 1;
            $token['token_type'] = 'entity';
            $token['token_text'] = $this->string_entity;
            $this->token_list[] = $token;
            $this->string_entity = '';
        }

    }
    private function _word_define($i, $k)
    {

        $token = [];
        $token['token_line'] = $i + 1;
        $token['token_type'] = 'define';
        $token['token_text'] = $this->split_line_list[$i][$k];
        $this->token_list[] = $token;

    }
    private function _word_empty($i)
    {

        $token = [];
        $token['token_line'] = $i + 1;
        $token['token_type'] = 'escape';
        $token['token_text'] = $this->split_line_list[$i];
        $this->token_list[] = $token;

    }
    private function _word()
    {
        $this->split_line_list = explode("\n", $this->origin_string);
        foreach($this->split_line_list as &$split_line)
        {
            $split_line .= "\n";
        }
        for($i = 0, $j = count($this->split_line_list); $i < $j; $i ++)
        {
            $this->string_escape = '';
            $this->string_entity = '';
            if($this->split_line_list[$i] === "\n")
            {
                $this->_word_empty($i);
                continue;
            }
            for($k = 0, $l = strlen($this->split_line_list[$i]); $k < $l; $k ++)
            {
                if(preg_match($this->pattern_escape, $this->split_line_list[$i][$k]) === 1)
                {
                    $this->_word_entity($i);
                    $this->string_escape .= $this->split_line_list[$i][$k];
                    continue;
                }
                if(preg_match($this->pattern_define, $this->split_line_list[$i][$k]) === 1)
                {
                    $this->_word_entity($i);
                    $this->_word_escape($i);
                    $this->_word_define($i, $k);
                    continue;
                }
                if(preg_match($this->pattern_entity, $this->split_line_list[$i][$k]) === 1)
                {
                    $this->_word_escape($i);
                    $this->string_entity .= $this->split_line_list[$i][$k];
                }
            }
            $this->_word_entity($i);
            $this->_word_escape($i);
        }
    }
    private function _mark()
    {

        for($i = 0, $j = count($this->token_list); $i < $j; $i ++)
        {
            if(empty($this->token_list[$i]))
            {
                continue;
            }
            if(isset($this->token_list[$i]['token_mark']))
            {
                continue;
            }
            if($this->is_string)
            {
                $this->token_list[$i]['token_mark'] = 'string';
                if($this->is_string_sq)
                {
                    if($this->token_list[$i]['token_text'] === '\'')
                    {
                        $bs = 0;
                        for($k = $i - 1; $k > 0; $k --)
                        {
                            if($this->token_list[$k]['token_text'] !== '\\')
                            {
                                break;
                            }
                            $bs ++;
                        }
                        if($bs % 2 === 0)
                        {
                            $this->is_string = FALSE;
                            $this->is_string_sq = FALSE;
                            continue;
                        }
                        continue;
                    }
                    continue;
                }
                if($this->is_string_dq)
                {
                    if($this->token_list[$i]['token_text'] === '"')
                    {
                        $bs = 0;
                        for($k = $i - 1; $k > 0; $k --)
                        {
                            if($this->token_list[$k]['token_text'] !== '\\')
                            {
                                break;
                            }
                            $bs ++;
                        }
                        if($bs % 2 === 0)
                        {
                            $this->is_string = FALSE;
                            $this->is_string_dq = FALSE;
                            continue;
                        }
                        continue;
                    }
                    continue;
                }
                if($this->is_string_dc)
                {
                    if($this->token_list[$i]['token_text'] === $this->eof)
                    {
                        if(preg_match('/\n/', $this->token_list[$i - 1]['token_text']) === 1 && preg_match('/[,;\n]/', $this->token_list[$i + 1]['token_text']))
                        {
                            $this->is_string = FALSE;
                            $this->is_string_dc = FALSE;
                            continue;
                        }
                    }
                }
                continue;
            }
            if($this->is_comment)
            {
                $this->token_list[$i]['token_mark'] = 'comment';
                if($this->is_comment_fk)
                {
                    if(preg_match('/\n/', $this->token_list[$i]['token_text']) === 1)
                    {
                        $this->is_comment = FALSE;
                        $this->is_comment_fk = FALSE;
                        continue;
                    }
                    continue;
                }
                if($this->is_comment_ak)
                {
                    if(preg_match('/\*/', $this->token_list[$i]['token_text']) === 1)
                    {
                        if($i + 1 < $j)
                        {
                            if($this->token_list[$i + 1]['token_text'] === '/')
                            {
                                $this->is_comment = FALSE;
                                $this->is_comment_ak = FALSE;
                                $this->token_list[$i + 1]['token_mark'] = 'comment';
                                continue;
                            }
                            continue;
                        }
                        continue;
                    }
                    continue;
                }
                if($this->is_comment_hk)
                {
                    if(preg_match('/\n/', $this->token_list[$i]['token_text']) === 1)
                    {
                        $this->is_comment = FALSE;
                        $this->is_comment_hk = FALSE;
                        continue;
                    }
                    continue;
                }
                continue;
            }
            if(in_array($this->token_list[$i]['token_text'], $this->keyword_list))
            {
                $this->token_list[$i]['token_mark'] = 'keyword';
                continue;
            }
            if($this->token_list[$i]['token_text'] === '\'')
            {
                if(!$this->is_comment && !$this->is_string)
                {
                    $this->is_string = TRUE;
                    $this->is_string_sq = TRUE;
                    $this->token_list[$i]['token_mark'] = 'string';
                    continue;
                }
            }
            if($this->token_list[$i]['token_text'] === '"')
            {
                if(!$this->is_comment && !$this->is_string)
                {
                    $this->is_string = TRUE;
                    $this->is_string_dq = TRUE;
                    $this->token_list[$i]['token_mark'] = 'string';
                    continue;
                }
            }
            if($this->token_list[$i]['token_text'] === '/')
            {
                if(!$this->is_comment && !$this->is_string)
                {
                    if($i + 1 < $j)
                    {
                        if($this->token_list[$i + 1]['token_text'] === '/')
                        {
                            $this->is_comment = TRUE;
                            $this->is_comment_fk = TRUE;
                            $this->token_list[$i]['token_mark'] = 'comment';
                            $this->token_list[$i + 1]['token_mark'] = 'comment';
                            continue;
                        }
                        if($this->token_list[$i + 1]['token_text'] === '*')
                        {
                            $this->is_comment = TRUE;
                            $this->is_comment_ak = TRUE;
                            $this->token_list[$i]['token_mark'] = 'comment';
                            $this->token_list[$i + 1]['token_mark'] = 'comment';
                            continue;
                        }
                    }
                }
            }
            if($this->token_list[$i]['token_text'] === '#')
            {
                if(!$this->is_comment && !$this->is_string)
                {
                    $this->is_comment = TRUE;
                    $this->is_comment_hk = TRUE;
                    $this->token_list[$i]['token_mark'] = 'comment';
                    continue;
                }
            }
            if($this->token_list[$i]['token_text'] === '<')
            {
                if($i + 1 < $j && $this->token_list[$i + 1]['token_text'] === '?' && $i + 2 < $j && $this->token_list[$i + 2]['token_text'] === 'php')
                {
                    $this->token_list[$i]['token_mark'] = 'keyword';
                    $this->token_list[$i + 1]['token_mark'] = 'keyword';
                    $this->token_list[$i + 2]['token_mark'] = 'keyword';
                    continue;
                }
                if($i + 1 < $j && $this->token_list[$i + 1]['token_text'] === '<' && $i + 2 < $j && $this->token_list[$i + 2]['token_text'] === '<')
                {
                    $eof = '';
                    for($k = $i + 3; $k < $j; $k ++)
                    {
                        if(preg_match('/\n/', $this->token_list[$k]['token_text']) === 1)
                        {
                            break;
                        }
                        $eof .= $this->token_list[$k]['token_text'];
                    }
                    if($eof !== '')
                    {
                        for(; $k > $i; $k --)
                        {
                            $this->token_list[$k]['token_mark'] = 'string';
                        }
                        $this->eof = trim($eof, '\'"');
                        $this->is_string = TRUE;
                        $this->is_string_dc = TRUE;
                        continue;
                    }
                }
            }
        }

    }


    public function __construct($origin_string)
    {

        $this->origin_string = $origin_string;

    }
    public function parse()
    {

        $this->_word();
        $this->_mark();
        return $this->token_list;

    }


}


$lexer = new Lexer(<<<'cmd'
<?php
$a = 123;
$b = 'Hello World!';
cmd);
var_dump($lexer->parse());

