<?php
/*
SimDemocracy Vote Token App, for Reddit
Copyright kuilin 2019, not released under any license.
This source code is provided for transparency only. You may not use this code.
*/

$reddit_secret = "[redacted]";
$app_secret = "[redacted]";
$reddit_clientid = "delF61_qTBGBMQ";
$useragent = "sdvotetokenapp/v0.1 for /r/simdemocracy by /u/kuilin";

if (isset($_GET["source"])) die(
        str_replace($app_secret, "[redacted]", 
            str_replace($reddit_secret, "[redacted]", 
                highlight_file(__FILE__, true)
            )
        )
    );
?>
<!doctype html>
<html>
    <head>
        <title>SimDemocracy Vote Token</title>
        <style>
            .main {
                border: 1px solid black;
                padding: 20px;
                margin: 20px auto;
                width: 400px;
                text-align: center;
            }
            .title {
                display: block;
                margin: 20px auto;
                color: black;
                text-decoration: none;
                font-size: 2em;
                font-weight: bold;
                text-align: center;
            }
            .code {
                width: 400px;
                text-align: center;
            }
            .verify {
                width: 400px;
                height: 300px;
            }
            .footer { text-align: center; }
            a { color: blue; }
        </style>
    </head>
    <body>
        <a class="title" href=".">SimDemocracy Token Generator</a>
        <div class="main">
            <?php if (isset($_GET["verify"])) { ?>
                Verify codes here:<br />
                <form action="." method="post">
                    <textarea class="verify" name="verify"></textarea><br />
                    <input type="checkbox" name="showall" id="showall" checked />
                    <label for="showall">Show All</label> 
                    <input type="submit" value="Submit" />
                </form>
                <br />(Note: this does not verify timestamps, those must be correlated with submission time elsewhere).
            <?php } else if (isset($_POST["verify"])) { ?>
                Results:<br />
                <textarea class="verify" readonly><?php
                        function check($code, &$checked, $app_secret) {
                            $tok = split("\\.", $code);
                            if (count($tok) !== 3) return "Error: Not valid format.";
                            
                            $uid = $tok[0];
                            $ts = $tok[1];
                            $key = $tok[2];
                            
                            if (array_key_exists($uid, $checked)) return "Error: Duplicate user ID.";
                            $checked[$uid] = 1;
                            if ($key != substr(hash_hmac("sha512", $uid . "." . $ts, $app_secret), 0, 20))
                                return "Error: Incorrect key.";
                            
                            return "OK.";
                        }
                        $checked = array();
                        $all_valid = true;
                        foreach (preg_split("/\R/", $_POST["verify"]) as $code) {
                            $ok = check($code, $checked, $app_secret);
                            if ($ok === "OK.") {
                                if (!isset($_POST["showall"])) continue;
                            } else $all_valid = false;
                            echo htmlentities($code) . "\n" . $ok . "\n\n";
                        }
                        if ($all_valid) echo "All codes are valid!";
                ?></textarea>
            <?php } else if (!isset($_GET["code"]) && !isset($_POST["code"])) { ?>
                <a href="https://www.reddit.com/api/v1/authorize?client_id=<?php echo $reddit_clientid; ?>&response_type=code&state=yeet&redirect_uri=https://kuilin.net/sdvote/&duration=temporary&scope=identity">Login via Reddit</a> 
                (<a href="https://www.reddit.com/api/v1/authorize.compact?client_id=<?php echo $reddit_clientid; ?>&response_type=code&state=yeet&redirect_uri=https://kuilin.net/sdvote/&duration=temporary&scope=identity">alternate</a>)
            <?php } else if (isset($_GET["code"])) {
                // Self-POST the code, in order to prevent clicking the Back button from revealing
                // the actual token, since OAUTH calls us with GET always. Leaking the code is fine.
                ?>
                <form action="." method="post">
                    Loading...
                    <input type="hidden" name="code" value="<?php echo $_GET["code"]; ?>" />
                </form>
                <script type="text/javascript"> window.onload = function () { document.forms[0].submit(); } </script>
            <?php } else {
                // Verify Reddit 
                $url = 'https://' . $reddit_clientid . ':' . $reddit_secret . '@www.reddit.com/api/v1/access_token';

                $options = array(
                    'http' => array(
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method'  => 'POST',
                        'user_agent' => $useragent,
                        'content' => http_build_query(array(
                            'grant_type' => 'authorization_code', 
                            'code' => $_POST["code"], 
                            'redirect_uri' => 'https://kuilin.net/sdvote/'
                        )),
                    ),
                );
                
                $context = stream_context_create($options);
                $result = @file_get_contents($url, false, $context);
                if ($result === false) die("An error occurred.");

                $res = json_decode($result, true);
                
                if (isset($res["error"]) || !isset($res["access_token"])) {
                    echo "Login failed :(";
                } else {
                    $url = 'https://oauth.reddit.com/api/v1/me';
                    
                    $options = array(
                        'http' => array(
                            'header' => "Authorization: bearer ".$res["access_token"],
                            'method' => 'GET', 
                            'user_agent' => $useragent
                        ),
                    );

                    $context = stream_context_create($options);
                    $result = @file_get_contents($url, false, $context);
                    if ($result === false) die("An error occurred.");
                    
                    $res = json_decode($result, true);
                    
                    $code = substr(hash_hmac("sha512", $res["name"], $app_secret), 0, 20);
                    $code .= "." . time();
                    $code .= "." . substr(hash_hmac("sha512", $code, $app_secret), 0, 20);
                    
                    ?> Your code:<br />
                        <input type="text" class="code" readonly value="<?php echo $code; ?>" onclick="this.select();" />
                        <br /><br />
                        Use this code to vote. <a href=".">Log Out</a>
                    <?php
                }
            } ?>
        </div>
        <div class="footer">Written <a href="/" target="_blank">by kuilin</a>, copyright 2019 |
            <a href="?source" target="_blank">Source</a> |
            <a href="https://github.com/likuilin/sdvote/issues" target="_blank">Issues</a> |
            <a href="?verify" target="_blank">Verify</a>
    </body>
</html>
