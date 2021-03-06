<?php
/** @var $filename string */

/** @var $newText string */
/** @var $newTitle string */

/** @var $currentText string */
/** @var $currentTtitle string */

/** @var $log string */

\Yii::$app->jsdifflib->register($this);

$this->title = "$projectName/$filename";
?>
<h1>Изменения <?= $this->title ?></h1>
<div class="diff">
    <?=yii\bootstrap\BaseHtml::icon(
        'minus',
        [
            'style' => 'float: right ;border: solid 1px #eee; cursor: pointer',
            'onclick' => 'togglePanelMode()',
        ]
    ); ?>
    <div class="item">
        <input type="checkbox" id="inline" style="float: left" onchange="diffUsingJS();"/>
        <label for="inline">В одну колонку</label>
    </div>

    <div class="item">
        <input type="checkbox" id="only-changed-rows" style="float: left" onchange="applyOnlyChangedRows();">
        <label for="only-changed-rows">Отфильтровать только изменения</label>
    </div>

    <div class="item">
        <button onclick="scrollNext()">Перейти к след. изменению</button>
    </div>
    <div class="item">
        <?php
        $diffStat = \Yii::$app->diffStat->getDiffStat(str_replace("\r", "", $currentText), str_replace("\r", "", $newText));
        $diffStat = preg_replace('~\++~', '<span style="color: #32cd32">$0</span>', $diffStat);
        $diffStat = preg_replace('~\-+~', '<span style="color: red">$0</span>', $diffStat);
        ?>
        <?= $diffStat ?>
    </div>
    <div style="clear: both"></div>
</div>

<br/>
<div id="baseText" style="display: none"><?= htmlspecialchars($currentText) ?></div>
<div id="newText" style="display: none"><?= htmlspecialchars($newText) ?></div>
<div id="diffoutput"></div>

<h1>Log</h1>
<pre><?= htmlspecialchars($log) ?></pre>

<script>
    function togglePanelMode() {
        $('div.diff').toggleClass('navbar-fixed-top')
    }

    function scrollNext() {
        $('div.diff').addClass('navbar-fixed-top');
        var currentItem = null;
        if (typeof this.index == "undefined") {
            currentItem = $('.non-equal').first();
            this.index = 0;
        } else {
            currentItem = $('.non-equal:gt(' + this.index + ')').first();

            var index = currentItem.index();
            do {
                this.index++;
                index++;
            } while ($('table.diff>tbody>tr:eq(' + index + ')').hasClass('non-equal'));

            if (currentItem.length == 0) {
                currentItem = $('.non-equal').first();
                this.index = 0;
            }
        }
        $('body').scrollTo(currentItem, 250);
    }

    function applyOnlyChangedRows() {
        if (document.getElementById('only-changed-rows').checked) {
            $('.diff tr').each(function (a, b) {
                if ($('td.insert, td.delete, td.empty, td.replace', $(b)).length == 0) $(b).hide();
            });
        } else {
            $('.diff tr').show();
        }
    }

    function diffUsingJS() {
        // get the baseText and newText values from the two textboxes, and split them into lines
        var base = difflib.stringAsLines(document.getElementById("baseText").innerHTML);
        var newtxt = difflib.stringAsLines(document.getElementById("newText").innerHTML);

        // create a SequenceMatcher instance that diffs the two sets of lines
        var sm = new difflib.SequenceMatcher(base, newtxt);

        // get the opcodes from the SequenceMatcher instance
        // opcodes is a list of 3-tuples describing what changes should be made to the base text
        // in order to yield the new text
        var opcodes = sm.get_opcodes();
        var diffoutputdiv = $("#diffoutput");
        diffoutputdiv.html('');
        // build the diff view and add it to the current DOM
        diffoutputdiv.html(diffview.buildView({
            baseTextLines: base,
            newTextLines: newtxt,
            opcodes: opcodes,
            // set the display titles for each resource
            baseTextName: <?=json_encode($currentTitle)?>,
            newTextName: <?=json_encode($newTitle)?>,
            contextSize: null,
            viewType: $("#inline")[0].checked ? 1 : 0
        }));

        setTimeout(function () {
            applyOnlyChangedRows();
            $('.insert, .replace, .empty, .delete').each(function (a, b) {
                $(b).parent().addClass('non-equal');
            });
        }, 0);
    }

    diffUsingJS();
</script>
