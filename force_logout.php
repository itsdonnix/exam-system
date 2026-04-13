<?php
session_start();
session_destroy();
echo "All sessions cleared. Users must login again.";
