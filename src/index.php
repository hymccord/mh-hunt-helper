<?php
    $title = "MouseHunt Community Tools";
    $css = "styles/main.css";
    $js = "scripts/main.js";
    $hide_home_link = true;
    $hide_footer_note = true;
    require_once "common-header.php";
?>

    <div class="col-md-6 col-md-offset-3">
        <table class="table text-center table-responsive table-bordered">
            <thead>
            <!-- <h3>MouseHunt Tools <span class="glyphicon glyphicon-flag"></span></h3> -->
            </thead>
            <tbody>
            <tr><td colspan="2">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Copy/Paste (OR Automatically filled by extension below) a list of your map mice into this tool,
                    and it will show you locations with stages, cheese, attraction rates, and mice in each area, sorted by descending
                    number of mice in each location."></a>
                <a href="/maphelper.php" style="display:block;text-decoration:none;color:#333;">Map Helper</a>
            </td></tr>
            <tr><td colspan="2">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Copy/Paste a list of your scavenger map items into this tool,
                    and it will show you locations with stages, cheese, drop rates, and items in each area, sorted by descending
                    number of items in each location."></a>
                <a href="/scavhelper.php" style="display:block;text-decoration:none;color:#333;">Scavenger Helper</a>
            </td></tr>
            <tr><td colspan="2">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Search by mouse name to see the location, cheese and attraction rate of a specific mouse."></a>
                <a href="/attractions.php" style="display:block;text-decoration:none;color:#333;">Attraction Rate</a>
            </td></tr>
            <tr><td colspan="2">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Search by loot name to see the location and drop rate of a specific loot."></a>
                <a href="/loot.php" style="display:block;text-decoration:none;color:#333;">Looter</a>
            </td></tr>
            <tr><td width="50%">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Search maps to see what mice are likely to appear on them."></a>
                <a href="/mapper.php" style="display:block;text-decoration:none;color:#333;">Mapper</a>
                </td>
                <td width="50%">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Search for a mouse to see which maps it's likely to appear on."></a>
                <a href="/reverse-mapper.php" style="display:block;text-decoration:none;color:#333;">Reverse Mapper</a>
            </td></tr>
            <tr><td width="50%">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Search convertibles like chests, to see what items are likely to be in them."></a>
                <a href="/converter.php" style="display:block;text-decoration:none;color:#333;">Converter</a>
                </td>
                <td width="50%">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Search to see which convertibles are likely to have the item you looking for."></a>
                <a href="/reverse-converter.php" style="display:block;text-decoration:none;color:#333;">Reverse Converter</a>
            </td></tr>
            <tr><td colspan="2">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="See confirmed RH location. (RH must be caught by one of us for it to show up here)"></a>
                <a href="/rh-tracker.php" style="display:block;text-decoration:none;color:#333;">Relic Hunter Tracker</a>
            </td></tr>
            <tr><td colspan="2">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="Check when event starts or ends."></a>
                <a href="/countdown.php" style="display:block;text-decoration:none;color:#333;">Events Countdown</a>
            </td></tr>
            <tr><td colspan="2">
                <a tabindex="0" class="glyphicon glyphicon-question-sign pull-right popover_styles" role="button" data-toggle="popover"
                    data-content="See stats about these tools."></a>
                <a href="/tracker.php" style="display:block;text-decoration:none;color:#333;">Stats</a>
            </td></tr>
            <tr><td colspan="2">
                <a href="/faq.php" style="display:block;text-decoration:none;color:#333;">F.A.Q.</a>
            </td></tr>
            <tr><td colspan="2">
                <a href="/contributors.php" style="display:block;text-decoration:none;color:#333;">Contributors/Sponsors <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="red" class="bi bi-balloon-heart-fill" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8.49 10.92C19.412 3.382 11.28-2.387 8 .986 4.719-2.387-3.413 3.382 7.51 10.92l-.234.468a.25.25 0 1 0 .448.224l.04-.08c.009.17.024.315.051.45.068.344.208.622.448 1.102l.013.028c.212.422.182.85.05 1.246-.135.402-.366.751-.534 1.003a.25.25 0 0 0 .416.278l.004-.007c.166-.248.431-.646.588-1.115.16-.479.212-1.051-.076-1.629-.258-.515-.365-.732-.419-1.004a2.376 2.376 0 0 1-.037-.289l.008.017a.25.25 0 1 0 .448-.224l-.235-.468ZM6.726 1.269c-1.167-.61-2.8-.142-3.454 1.135-.237.463-.36 1.08-.202 1.85.055.27.467.197.527-.071.285-1.256 1.177-2.462 2.989-2.528.234-.008.348-.278.14-.386Z"/>
                </svg></a>
            </td></tr>
            </tbody>
        </table>
    </div>
</div>
<div class="container">
    <p class="muted">
        If you'd like to contribute, please install the browser extension below and consider supporting us through <a href="donations.php">Donations</a>!
        <br/>Our code is open source on <a href="https://github.com/m-h-c-t" target="_blank">GitHub</a> and SQL database dumps can be downloaded from <a href="https://keybase.pub/devjacksmith/mh_backups/" target="_blank">Keybase</a> or <a href="https://hub.docker.com/r/tsitu/mhct-db-docker" target="_blank">Docker</a>.
        <br/>Also check out <a href="https://www.reddit.com/r/mousehunt/" target="_blank">/r/mousehunt</a> on Reddit and join the conversation on <a href="https://discord.gg/E4utmBD" target="_blank">Discord</a>.
        <br/>Maintained by the MHCT team. <b>Thank you</b> to all of our contributors!
    </p>
</div>
<div class="container">
    <div class="text-center">
        <a href="https://chrome.google.com/webstore/detail/mhct-mousehunt-helper/ghfmjkamilolkalibpmokjigalmncfek?authuser=1" target="_blank" style="display:inline-block;text-decoration:none;color:#333;"><img src="images/chrome.png"></a>
        <a href="https://addons.mozilla.org/en-US/firefox/addon/mhct-mousehunt-helper/" target="_blank" style="display:inline-block;text-decoration:none;color:#333;"><img src="images/firefox.png"><b> Add-on</b></a>
        <br/><a href="https://github.com/m-h-c-t" target="_blank"><img src="images/github.png" style="width:100px;margin-top:10px;margin-right:10px;"></a>
    </div>
</div>
<?php require_once "common-footer.php"; ?>
