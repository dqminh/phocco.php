<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html;charset=utf-8">
        <title><?php echo $this->title ?></title>
        <link rel="stylesheet" href="http://jashkenas.github.com/docco/resources/docco.css">
    </head>
    <body>
        <div id='container'>
            <div id="background"></div>
            <?php if (isset($this->sources) && !empty($this->sources)): ?>
            <div id="jump_to">
                Jump To &hellip;
                <div id="jump_wrapper">
                    <div id="jump_page">
                        <?php foreach ($this->sources['sources'] as $source): ?>
                        <a class="source" href="<?php echo $source['url'] ?>"><?php echo $source['basename'] ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <table cellspacing=0 cellpadding=0>
                <thead>
                    <tr>
                        <th class=docs><h1><?php echo $this->title ?></h1></th>
                        <th class=code></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->sections as $section): ?>
                    <tr id="section-<?php echo $section['num'] ?>">
                        <td class=docs>
                            <div class="pilwrap">
                                <a class="pilcrow" href="#section-<?php echo $section['num'] ?>">#</a>
                            </div>
                            <?php echo $section['docs_html'] ?>
                        </td>
                        <td class=code>
                        <div class='highlight'><pre><?php echo $section['code_html'] ?></pre></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
            </table>
        </div>
    </body>
</html>
