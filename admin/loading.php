<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        .loader {
            border-radius: 50%;
            width: 300px;
            height: 300px;
            animation: spin 2s linear infinite;

            border-top: 16px solid blue;
            border-right: 16px solid green;
            border-bottom: 16px solid red;
            border-left: 16px solid pink;

            position: absolute;
            left: 40%;

        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .center-container {
            text-align: center;
            position: relative;
            /* Add relative positioning */
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="center-container">
        <h1>loading</h1>
        <div class="loader"></div>
    </div>
</body>

</html>