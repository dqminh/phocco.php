#!/usr/bin/php
<?php
// We will use [Markdown.php] (http://michelf.com/projects/php-markdown/)
// and [PHP-Optparse] (https://github.com/lelutin/php-optparse)
include_once('libs/markdown.php');
include_once('libs/optparse.php');

// Remote web service to highlight code
define ("PYGMENT_WEB_SERVICE", "http://pygments.appspot.com");

// Code is run through [Pygments] (http://pygments.org/) for syntax
// highlighting. If it's not installed, locally, use a webservice
// 
// is Pygment presented in the local path
$envPaths = explode(":", getenv("PATH"));
$isPygmentLocal = false;

foreach ($envPaths as $dir) {
    if (is_executable($dir . "/pygmentize")) {
        $isPygmentLocal = true;
        break;
    }
}

if (!$isPygmentLocal) {
    echo "WARNING: Pygments not found. Using webservice";
}

// a list of languages that phocco support, mapping the file extension to
// the name of the Pygments lexer and the symbol that indicates a comment.
// To add another language to Phocco's repertoire, add it here
$languages = array(
    '.php' => array('name' => 'php', 'symbol' => '//')
);

foreach ($languages as $ext => &$l) {
    // regex to determine the line begins with a comment
    $l["comment_matcher"] = "#^\\s*" . $l['symbol'] . "#";

    $l["divider_text"] = "\n" . $l['symbol'] . "DIVIDER\n";

    $l["divider_html"] = '#\n*<span class="c[1]?">' . $l['symbol']
        . 'DIVIDER</span>\n*#';
}

// The CSS styles we would like to apply to the documentation
$phoccoStyles = "phocco.css";

// The start of each Pygments highlight block
$highlight_start = '<div class="highlight"><pre>';

// The end of each Pygments highlight block
$highlight_end = '</pre></div>';

//////// Main Documentation Generation Interface
//
//
class Phocco {
    // Get the language of the current source file, based on extension
    public function getLanguage($source) {
        global $languages;
        return $languages[substr($source, strrpos($source, "."))];
    }

    // Generate documentation of a source file by reading it in, splitting it
    // up into comment/code section, highlighting them for the approriate
    // language, and merging them into an HTML template
    public function generateDocumentation($source, $options = array()) {
        $handle = fopen($source, "r");
        $sections = $this->parse($source, fread($handle, filesize($source)));
        $this->highlight($source, $sections, $options);
        $this->generateHtml($source, $sections, $options);
    }


    // Given a string of source code, parse out each comment and the code that
    // follows it, and create an individual **section** for it
    // Sections take the form:
    //
    //      array(
    //          "docs_text" => ...,
    //          "docs_html" => ...,
    //          "code_text" => ...,
    //          "code_html" => ...,
    //          "num" => ...
    //      )
    public function parse($source, $code) {
        $lines = explode("\n", $code);
        $sections = array();
        $language = $this->getLanguage($source);
        $docs_text = $code_text = "";

        if (strpos($lines[0], "#!") !== false) {
            unset($lines[0]);
        }

        $save = function($docs, $code) use (&$sections){
            $sections[] = array(
                "docs_text" => $docs,
                "code_text" => $code
            );
        };

        foreach ($lines as $line) {
            if (preg_match($language['comment_matcher'], $line)) {
                if ($code_text) {
                    $save($docs_text, $code_text);
                    $docs_text = $code_text = "";
                }
                $docs_text .= preg_replace(
                    $language['comment_matcher'],
                    "",
                    $line) . "\n";
            } else {
                $code_text .= $line . "\n";
            }
        }

        $save($docs_text, $code_text);
        return $sections;
    }

    public function destination($filePath, $options = array()) {

        function joinPaths() {
            $args = func_get_args();
            $paths = array();
            foreach ($args as $arg) {
                $paths = array_merge($paths, (array)$arg);
            }
            foreach ($paths as &$path) {
                $path = trim($path, '/');
            }
            return join('/', $paths);
        }

        $preservePaths = $options['paths'];

        if (($lastDot = strrpos($filePath, ".")) !== false) {
            $name = str_replace(substr($filePath, $lastDot), "", $filePath);
        } else {
            $name = $filePath;
        }
        $name = str_replace(substr($filePath, strrpos($filePath, ".")), "",
            $filePath);

        if (!isset($options['paths'])) {
            $name = basename($name);
        }

        return joinPaths($options['dir'], $name . ".html");
    }

    // === Highlighting the source code ===
    //
    // Highlights a single chunk of code using the **Pygments** module, and runs the
    // text of its corresponding comment through **Markdown**.
    // We process the entire file in a single call to Pygments by inserting little
    // marker comments between each section and then splitting the result string
    // wherever our markers occur.
    public function highlight($source, &$sections, $options = array()) {
        global $isPygmentLocal, $highlight_end, $highlight_start;
        $language = $this->getLanguage($source);
        $tobePygmentize = "";

        foreach ($sections as $key => $section) {
            if ($key == 0) {
                $tobePygmentize .= $section['code_text'];
            } else {
                $tobePygmentize .= $language['divider_text'].$section['code_text'];
            }
        }


        $output = $this->hightlightPygmentize($tobePygmentize,
            $language['name']);

        $output = str_replace($highlight_start, "", $output);
        $output = str_replace($highlight_end, "", $output);
        $fragments = preg_split($language["divider_html"], $output);

        $shift = function($array, $default) {
            if (isset($array[0])) {
                $val = $array[0];
                unset($array[0]);
                return $val;
            }
        };

        foreach ($sections as $i => &$section) {
            $beginVal = array_shift($fragments);
            if ($beginVal == null) $beginVal = "";
            $section['code_html'] = $highlight_start . $beginVal
                . $highlight_end; 
            $docs_text = utf8_encode($section['docs_text']);
            $section['docs_html'] = Markdown($docs_text);
            $section['num'] = $i;
        }
    }

    // === HTML Code generation ===
    //
    // Once all of the code is finished highlighting, we can generate the HTML file
    // and write out the documentation. Pass the completed sections into the template
    // found in `resources/phocco_template.php`
    public function generateHtml($source, $sections, $options) {
        $title = basename($source);
        $dest = $this->destination($source, $options);

        $html = new Template('resources/phocco_template.php', array(
            'title' => $title,
            'sections' => $sections,
            'sources' => $source,
            'destination' => $dest
        ));

        echo sprintf("phocco = %s -> %s \n", $source, $dest);

        $pathParts = pathinfo($dest);
        if (isset($pathParts['dirname'])) {
            if (!is_dir($pathParts['dirname'])) {
                mkdir($pathParts['dirname']);
            }
            // getting ouput from view as a string
            ob_start();
            $html->render();
            $output = ob_get_clean();

            $handle = fopen($dest, 'w');
            fwrite($handle, $output);
            fclose($handle);
        }
    }

    private function hightlightPygmentize($source, $language) {
        $output = array();

        $descriptor = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("file", "/tmp/error-output.txt", "a")
        );
        $cwd = '/tmp';

        $process = proc_open('pygmentize -f html -l ' . $language, $descriptor,
            $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $source);
            fclose($pipes[0]);

            $contents = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            proc_close($process);
            $contents = str_replace("<?php", "", $contents, $count);
            $contents = str_replace("?>", "", $contents);
            return $contents;
        }
    }
}

class Template {
    private $args;
    private $file;

    public function __get($name) {
        return $this->args[$name];
    }

    public function __construct($file, $args = array()) {
        $this->file = $file;
        $this->args = $args;
    }

    public function render() {
        include $this->file;
    }
}

function ensureDirectory($directory) {
    if (!is_dir($directory)) {
        mkdir($directory);
    }
}

// The bulk of the work is done here
// For each source file passed in an argument, generate the documentation
function process($sources, $options) {
    if (!empty($sources)) {
        sort($sources);
        ensureDirectory($options['dir']);

        // run the script
        $phocco = new Phocco;
        
        while ($sources != null) {
            $phocco->generateDocumentation(array_pop($sources), $options);
        }
    }
}

$parser = new OptionParser();
$parser->add_option(array('-p', '--paths', 'dest' => 'paths'));
$parser->add_option(array('-d', '--directory', 'dest' => 'dir',
    'default' => 'docs',
    'type' => 'string'));

$options = $parser->parse_args($argv);

process($options->positional, $options->options);
?>
