<?php
//Window for play.php overlay
class window
{
    public static function open($title, $width = 400, $height = 300)
    {
        echo "<div class='window-overlay'>";
        echo "<div class='window' style='width: {$width}px; height: {$height}px;'>";
        echo "<div class='window-header'>";
        echo "<h2>{$title}</h2>";
        echo "<button class='window-close' onclick='closeWindow()'>X</button>";
        echo "</div>";
        echo "<div class='window-content'>";
    }

    public static function close()
    {
        echo "</div>"; // Close window-content
        echo "</div>"; // Close window
        echo "</div>"; // Close window-overlay
    }
}

?>