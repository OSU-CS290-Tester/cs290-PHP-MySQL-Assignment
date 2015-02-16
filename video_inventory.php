<?php
ini_set('display_errors', 'On');

function connectDB()
{
    include 'storedInfo.php';
    $mysqli = new mysqli("oniddb.cws.oregonstate.edu","thomkevi-db", $myPassword, "thomkevi-db");
    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    } else {
        /* echo "Connection worked!<br>"; */
    }
    return $mysqli;
}

if (isset($_POST['delete_all']))
{
    $mysqli = connectDB();
    /* Prepared statement, stage 1: prepare */
    if (!($stmt = $mysqli->prepare("DELETE FROM VIDEO_INVENTORY"))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    /* Execute Statement */
    if (!$stmt->execute()) {
        echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    /* explicit close recommended */
    $stmt->close();
}

if (isset($_POST['deleteVideo']))
{
    $mysqli = connectDB();
    /* Prepared statement, stage 1: prepare */
    if (!($stmt = $mysqli->prepare("DELETE FROM VIDEO_INVENTORY WHERE ID = ?"))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    $vid_id = $_POST['deleteVideo'];
    if (!$stmt->bind_param("i", $vid_id)) {
        echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    /* Execute Statement */
    if (!$stmt->execute()) {
        echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    /* explicit close recommended */
    $stmt->close();
}

if (isset($_POST['toggleStatus']))
{
    $mysqli = connectDB();
    /* Prepared statement, stage 1: prepare */
    if (!($stmt = $mysqli->prepare("UPDATE VIDEO_INVENTORY SET RENTED = !RENTED  WHERE ID = ?"))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    $vid_id = $_POST['toggleStatus'];
    if (!$stmt->bind_param("i", $vid_id)) {
        echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    /* Execute Statement */
    if (!$stmt->execute()) {
        echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    /* explicit close recommended */
    $stmt->close();
}

if (isset($_POST['action']) && $_POST['action'] == 'add_video' ) {

    $mysqli = connectDB();
    /* Prepared statement, stage 1: prepare */
    if (!($stmt = $mysqli->prepare("INSERT INTO VIDEO_INVENTORY (name, category, length) VALUES (?, ?, ?)"))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    /* Prepared statement, stage 2: bind and execute */
    $vid_name = $_POST['video_name'];
    $vid_cat = $_POST['video_category'];
    $vid_len = $_POST['video_length'];
    if ($vid_len == 0)
    {
        $vid_len = NULL;
    }
    if (!$stmt->bind_param("ssi", $vid_name, $vid_cat, $vid_len)) {
        echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    /* Execute Statement */
    if (!$stmt->execute()) {
        echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    /* explicit close recommended */
    $stmt->close();
}

function executeSelect()
{
    $mysqli = connectDB();

    $selectStmt = "SELECT id, name, category, length, rented FROM VIDEO_INVENTORY";

    if (isset($_POST['filter']) && $_POST['filter'] != 'all')
    {
        $selectStmt = $selectStmt . " WHERE category = ?";
    }

    if (!($stmt = $mysqli->prepare($selectStmt))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error . $selectStmt;
    }

    if (isset($_POST['filter']) && $_POST['filter'] != 'all') {
        $filter = $_POST['filter'];
        if (!$stmt->bind_param("s", $filter)) {
            echo "Binding parameters failed: (" . $stmt->errno . ") " . $stmt->error;
        }
    }

    /* Execute Statement */
    if (!$stmt->execute()) {
        echo "Execute failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    $out_id = NULL;
    $out_name = NULL;
    $out_cat = NULL;
    $out_len = NULL;
    $out_rented = NULL;
    if (!$stmt->bind_result($out_id, $out_name, $out_cat, $out_len, $out_rented)) {
        echo "Binding output parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    while ($stmt->fetch()) {
        echo "<tr><td>$out_name<td>$out_cat<td>$out_len";
        $clickAction = "{toggleStatus: '$out_id'}";
        if ($out_rented == 0) {
            echo "<td>Available<td><input type='submit' value='Check Out' " . 'onclick="updateVideo(' . $clickAction . ')">';
        } else {
            echo "<td>Checked Out<td><input type='submit' value='Check In' " . 'onclick="updateVideo(' . $clickAction . ')">';
        }
        $clickAction = "{deleteVideo: '$out_id'}";
        echo "<td><input type='submit' value='Delete' " . 'onclick="updateVideo(' . $clickAction . ')">';
    }

    /* explicit close recommended */
    $stmt->close();
}

function loadFilter() {
    $mysqli = connectDB();

    if (!($stmt = $mysqli->prepare("SELECT distinct category FROM VIDEO_INVENTORY WHERE TRIM(CATEGORY) != ''"))) {
        echo "Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    if (!$stmt->execute()) {
        echo "Execute failed: (" . $mysqli->errno . ") " . $mysqli->error;
    }

    $out_cat = NULL;

    if (!$stmt->bind_result($out_cat)) {
        echo "Binding output parameters failed: (" . $stmt->errno . ") " . $stmt->error;
    }

    echo "<option value='all' selected>All Movies</option>";

    while ($stmt->fetch()) {
        echo "<option value='$out_cat'>$out_cat</option>";
    }

    /* explicit close recommended */
    $stmt->close();
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Video Inventory</title>
    <script>
function validateVideo(form) {
    if (form.video_name.value.length < 1 || form.video_name.value.length > 255) {
        alert("Video Name Must Be Between 1 and 255 Characters");
        return false;
    }
    var vid_len = form.video_length.value;
    var n = ~~Number(vid_len);
    if (vid_len === "" || (String(n) === vid_len && n > 0)) {
        return true;
    } else {
        alert("Video Length Must Be Blank or a Positive Integer");
        return false;
    }
}
function updateVideo(params) {
    /* Credit: Rakesh Pai
     http://stackoverflow.com/questions/133925/javascript-post-request-like-a-form-submit
     */
    var method = "post";
    var path = "video_inventory.php";

    var form = document.createElement("form");
    form.setAttribute("method", method);
    form.setAttribute("action", path);

    for (var key in params) {
        if (params.hasOwnProperty(key)) {
            var hiddenField = document.createElement("input");
            hiddenField.setAttribute("type", "hidden");
            hiddenField.setAttribute("name", key);
            hiddenField.setAttribute("value", params[key]);

            form.appendChild(hiddenField);
        }
    }

    document.body.appendChild(form);
    form.submit();
}
    </script>
</head>
<body>
    <div>
        <p>
            <form action="video_inventory.php" method="POST" onsubmit="return validateVideo(this)">
                <table>
                    <tbody>
                        <tr>
                            <td>Name:
                            <td><input type="text" name="video_name">
                        <tr>
                            <td>Category:
                            <td><input type="text" name="video_category">
                        <tr>
                            <td>Length:
                            <td><input type="text" name="video_length">
                        <tr>
                            <td colspan="2"><input type="hidden" name="action" value="add_video">
                                <input type="submit" value="Add">
                    </tbody>
                </table>
            </form>
            <div id="add_video_error_message"></div>
        <p>
            <b>Inventory:</b>
            <form action="video_inventory.php" method="POST">
            <select name="filter">
                <?php
                    loadFilter();
                ?>
            </select>
            <input type="submit" value="Filter">
            </form>
            <br>
            <table>
            <thead>
            <tr>
                <th>Name
                <th>Category
                <th>Length
                <th>Status
                <th>Change Status
                <th>Delete
            </tr>
            </thead>
            <tbody>
            <?php

            executeSelect();

            ?>
            </tbody>
            </table>
        <p>
            <form action="video_inventory.php" method="POST">
            <input type="hidden" name="action" value="delete_all">
            <input type="submit" name="delete_all" value="Delete All Videos">
            </form>
    </div>
</body>
</html>