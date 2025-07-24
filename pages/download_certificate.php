<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

$promo_code = isset($_GET['promo_code']) ? sanitizeInput($_GET['promo_code']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Certificate</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url(../assets/view_bg.jpg) no-repeat center center fixed;
            background-size: cover;
            color: black;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            position: relative;
        }
        .card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            min-height: 300px;
            max-height: 350px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid #ddd;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .header {
            color: teal;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-control {
            border-color: teal;
        }
        .submit-btn {
            background-color: teal;
            color: white;
            font-weight: bold;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .submit-btn:hover {
            background-color: rgb(8, 95, 110);
        }
        .alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            max-width: 90%;
            width: 400px;
            text-align: center;
            animation: fadeInOut 2s ease-in-out;
        }
        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; }
        }
        @media (max-width: 600px) {
            .card {
                padding: 20px;
                max-width: 90%;
                min-height: 280px;
                max-height: 320px;
            }
            .header {
                font-size: 20px;
            }
            .alert {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="alert alert-success alert-dismissible fade show" id="successAlert" role="alert" style="display: none;">
        <span id="successMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <div class="alert alert-danger alert-dismissible fade show" id="errorAlert" role="alert" style="display: none;">
        <span id="errorMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <div class="card">
        <div class="header">Access Your Certificate</div>
        <form id="certificateForm" method="POST">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="promo_code">Promo Code:</label>
                <input type="text" class="form-control" id="promo_code" name="promo_code" value="<?php echo htmlspecialchars($promo_code); ?>" readonly>
                <input type="hidden" name="promo_code" value="<?php echo htmlspecialchars($promo_code); ?>">
            </div>
            <button type="submit" class="submit-btn">Submit</button>
        </form>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#certificateForm').on('submit', function(e) {
                e.preventDefault();
                let formData = $(this).serialize();
                $.ajax({
                    url: '../actions/validate_participant.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#successMessage').text('The certificate will be sent to your email ' + response.email + ' shortly.');
                            $('#successAlert').show();
                            setTimeout(function() {
                                $('#successAlert').fadeOut('slow', function() {
                                    $('#certificateForm')[0].reset();
                                    $('#promo_code').val('<?php echo htmlspecialchars($promo_code); ?>');
                                });
                            }, 2000);

                            // Trigger background processing
                            $.ajax({
                                url: '../actions/process_pending_task.php',
                                type: 'POST',
                                data: { task_id: response.task_id },
                                success: function(backendResponse) {
                                    if (backendResponse.status !== 'success') {
                                        console.error('Background processing failed:', backendResponse.message);
                                    }
                                },
                                error: function() {
                                    console.error('Error triggering background processing.');
                                }
                            });
                        } else {
                            $('#errorMessage').text(response.message);
                            $('#errorAlert').show();
                            setTimeout(function() {
                                $('#errorAlert').fadeOut('slow');
                            }, 2000);
                        }
                    },
                    error: function() {
                        $('#errorMessage').text('An error occurred. Please try again.');
                        $('#errorAlert').show();
                        setTimeout(function() {
                            $('#errorAlert').fadeOut('slow');
                        }, 2000);
                    }
                });
            });
        });
    </script>
</body>
</html>