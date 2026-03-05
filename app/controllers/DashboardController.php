<?php

class DashboardController extends Controller
{
    public function index()
    {
        requireLogin();

        echo "<h2>Welcome to DTS</h2>";
        echo "<p>You are logged in.</p>";
    }
}
