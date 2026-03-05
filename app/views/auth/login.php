<!DOCTYPE html>
<html>
<head>
    <title>Login - DTS</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-box {
            background: white;
            padding: 30px;
            width: 350px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
        }

        .login-box h2 {
            text-align: center;
        }

        input {
            width: 100%;
            padding: 8px;
            margin: 8px 0;
        }

        button {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Document Tracking System</h2>

    <form action="" method="POST">
        <input type="text" name="id_number" placeholder="ID Number" required>
        <input type="password" name="password" placeholder="Password" required>

        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
