<?php
include '../config.php';
include_once '../functions.php';
session_start();

if (!isset($_GET['event_name'])) {
    die("Error: Event name not provided.");
}

$event_name = filter_input(INPUT_GET, 'event_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (empty($event_name)) {
    die("Error: Invalid event name.");
}

$conn = getDatabaseConnection();
$stmt = $conn->prepare("SELECT visibility FROM templates WHERE event_name = ?");
$stmt->bind_param("s", $event_name);
$stmt->execute();
$result = $stmt->get_result();
$template = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$template || !$template['visibility']) {
    die("Error: No Event found with the name '$event_name'. Please check the event name or contact support.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event_name); ?> - Event Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: url('/assets/event_bg.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            flex: 1;
            padding: clamp(1rem, 3vw, 2rem) clamp(0.5rem, 2vw, 1rem);
        }
        .event-header {
            background: linear-gradient(135deg, #20c997, #ffc107);
            padding: clamp(1rem, 2.5vw, 1.5rem);
            border-radius: 15px;
            text-align: center;
            margin-bottom: clamp(1rem, 3vw, 2rem);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 100%;
        }
        .event-header h1 {
            font-size: clamp(1.5rem, 4vw, 2.2rem);
            font-weight: bold;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .card {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #20c997;
            border-radius: 10px;
            color: #333;
            margin-bottom: clamp(1rem, 3vw, 2rem);
        }
        .card-header {
            background: #20c997;
            color: #fff;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: clamp(0.75rem, 2vw, 1rem);
        }
        .btn-primary, .btn-import, .btn-download, .btn-accept, .btn-decline {
            background-color: #ffc107;
            border-color: #ffc107;
            transition: background-color 0.3s ease;
            color: #fff;
            padding: clamp(0.5rem, 1.5vw, 0.75rem);
            font-size: clamp(0.9rem, 2vw, 1rem);
        }
        .btn-primary:hover, .btn-import:hover, .btn-download:hover, .btn-accept:hover, .btn-decline:hover {
            background-color: #ffca2c;
            border-color: #ffca2c;
        }
        .form-control:disabled {
            background-color: #e9ecef;
            color: #6c757d;
        }
        .alert-success {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: none;
            font-size: clamp(0.9rem, 2vw, 1rem);
            padding: clamp(0.5rem, 1.5vw, 0.75rem);
        }
        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #20c997;
            border-radius: 10px;
        }
        .modal-header {
            background: #20c997;
            color: #fff;
            border-bottom: none;
            padding: clamp(0.75rem, 2vw, 1rem);
        }
        .modal-body .card {
            background: rgba(255, 255, 255, 1);
            border: 1px solid #20c997;
        }
        .modal-body .card-header {
            background: linear-gradient(135deg, #20c997, #ffc107);
            padding: clamp(0.5rem, 1.5vw, 0.75rem);
        }
        .conditions-list {
            padding-left: clamp(1rem, 2vw, 1.5rem);
            font-size: clamp(0.85rem, 1.8vw, 0.95rem);
        }
        .role-selection {
            display: flex;
            gap: clamp(0.5rem, 1.5vw, 1rem);
            margin-bottom: clamp(0.5rem, 1.5vw, 1rem);
        }
        .table-container {
            max-height: clamp(200px, 40vh, 300px);
            overflow-y: auto;
            margin-bottom: clamp(0.5rem, 1.5vw, 1rem);
        }
        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: clamp(0.8rem, 1.8vw, 0.9rem);
        }
        .table-container th, .table-container td {
            padding: clamp(6px, 1.5vw, 8px);
            border: 1px solid #ddd;
            text-align: left;
        }
        .table-container th {
            background-color: #20c997;
            color: #fff;
        }
        .success-row {
            color: green;
        }
        .failed-row {
            color: red;
        }
        .progress {
            height: clamp(15px, 3vw, 20px);
            margin-bottom: clamp(0.5rem, 1.5vw, 1rem);
        }
        @media (max-width: 768px) {
            .event-header {
                padding: clamp(0.75rem, 2vw, 1rem);
            }
            .event-header h1 {
                font-size: clamp(1.3rem, 3.5vw, 1.8rem);
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: clamp(0.3rem, 1vw, 0.5rem);
            }
            .btn-import, .btn-accept, .btn-decline {
                width: 100%;
                font-size: clamp(0.85rem, 2vw, 0.95rem);
            }
            .role-selection {
                flex-direction: column;
                gap: clamp(0.3rem, 1vw, 0.5rem);
            }
            .modal-dialog {
                margin: clamp(0.5rem, 2vw, 1rem);
            }
            .table-container {
                max-height: clamp(150px, 35vh, 250px);
            }
            .form-control {
                font-size: clamp(0.85rem, 2vw, 0.95rem);
            }
        }
        @media (max-width: 576px) {
            .event-header h1 {
                font-size: clamp(1.2rem, 3vw, 1.5rem);
            }
            .container {
                padding: clamp(0.5rem, 2vw, 1rem);
            }
            .card {
                margin-bottom: clamp(0.5rem, 2vw, 1rem);
            }
            .btn-primary {
                width: 100%;
            }
            .modal-content {
                padding: clamp(0.5rem, 1.5vw, 0.75rem);
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="event-header">
                    <h1><i class="fas fa-calendar-alt me-2"></i><?php echo htmlspecialchars($event_name); ?></h1>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-user-plus me-2"></i>Register for Event</span>
                        <button type="button" class="btn btn-import btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="fas fa-file-import me-1"></i>Import
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="registerForm" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <div class="role-selection">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="role" id="roleStudent" value="Student" checked>
                                        <label class="form-check-label" for="roleStudent">Student</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="role" id="roleFaculty" value="Faculty">
                                        <label class="form-check-label" for="roleFaculty">Faculty</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3" id="courseNameField">
                                <label class="form-label">Course Name (Optional)</label>
                                <input type="text" class="form-control" name="course_name">
                            </div>
                            <div class="mb-3" id="departmentNameField" style="display: none;">
                                <label class="form-label">Department Name (Optional)</label>
                                <input type="text" class="form-control" name="department_name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">College Name (Optional)</label>
                                <input type="text" class="form-control" name="college_name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Name</label>
                                <input type="text" class="form-control" name="event_name" value="<?php echo htmlspecialchars($event_name); ?>" disabled>
                                <input type="hidden" name="event_name" value="<?php echo htmlspecialchars($event_name); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>Register</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel"><i class="fas fa-file-import me-2"></i>Import Participants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="importForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="file" accept=".xls,.xlsx" required>
                            <button type="submit" class="btn btn-import w-100 mt-2"><i class="fas fa-upload me-1"></i>Import File</button>
                        </div>
                        <div class="mb-3">
                            <a href="/assets/sample_participants.xlsx" class="btn btn-download w-100" download><i class="fas fa-download me-1"></i>Download Sample</a>
                        </div>
                    </form>
                    <div class="card">
                        <div class="card-header">Conditions to Import Data<i class="fas fa-info-circle me-2"></i></div>
                        <div class="card-body">
                            <p>Please ensure your file meets the following requirements for successful import:</p>
                            <ul class="conditions-list">
                                <li>File must have the extension <strong>.xls</strong> or <strong>.xlsx</strong>.</li>
                                <li>File must contain columns in the exact sequence: <strong>Name</strong>, <strong>Email</strong>, <strong>Course Name</strong>, <strong>Department Name</strong>, <strong>College Name</strong>.</li>
                                <li>Column headers must exactly match <strong>Name</strong>, <strong>Email</strong>, <strong>Course Name</strong>, <strong>Department Name</strong>, <strong>College Name</strong> (case-sensitive).</li>
                                <li><strong>Course Name</strong> column (optional) should be filled for students if applicable; leave empty if not needed.</li>
                                <li><strong>Department Name</strong> column (optional) should be filled for faculty if applicable; leave empty if not needed.</li>
                                <li><strong>College Name</strong> column (optional) can be left empty; if empty, it will be stored as <strong>N/A</strong>.</li>
                                <li>Ensure mandatory fields are valid: names and emails should be non-empty, emails must be valid email addresses.</li>
                                <li>File size should not exceed 5MB to avoid upload issues.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Processing Modal -->
    <div class="modal fade" id="processingModal" tabindex="-1" aria-labelledby="processingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="processingModalLabel"><i class="fas fa-cog fa-spin me-2"></i>Processing Your File <span id="progressPercentage">0%</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closeProcessingModal"></button>
                </div>
                <div class="modal-body">
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" id="progressBar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="table-container">
                        <table class="table" id="processingTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Course Name</th>
                                    <th>Department Name</th>
                                    <th>College Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="processingTableBody"></tbody>
                        </table>
                    </div>
                    <div id="importSummary" class="mb-3" style="display: none;">
                        <p>Successful entries: <span id="successCount">0</span>, Failed entries: <span id="failedCount">0</span></p>
                    </div>
                    <div id="actionButtons" class="d-flex justify-content-end gap-2" style="display: none;">
                        <button type="button" class="btn btn-accept" id="acceptButton"><i class="fas fa-check me-1"></i>Accept</button>
                        <button type="button" class="btn btn-decline" id="declineButton"><i class="fas fa-times me-1"></i>Decline</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Alert -->
    <div class="alert alert-success alert-dismissible fade show" id="successAlert" role="alert" style="display: none;">
        <span id="successMessage"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle Course Name and Department Name fields based on role and clear the opposite field
            function toggleRoleFields() {
                if ($('#roleStudent').is(':checked')) {
                    $('#courseNameField').show();
                    $('#departmentNameField').hide();
                    $('input[name="department_name"]').val(''); // Clear Department Name field
                } else {
                    $('#courseNameField').hide();
                    $('#departmentNameField').show();
                    $('input[name="course_name"]').val(''); // Clear Course Name field
                }
            }

            // Initialize role fields on page load
            toggleRoleFields();

            // Update fields when role changes
            $('input[name="role"]').on('change', toggleRoleFields);

            // Handle registration form submission
            $('#registerForm').on('submit', function(e) {
                e.preventDefault();
                let formData = $(this).serialize();
                $.ajax({
                    url: '../actions/register_participant.php',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#successMessage').text(response.message);
                            $('#successAlert').show();
                            setTimeout(function() {
                                $('#successAlert').fadeOut('slow', function() {
                                    $('#registerForm')[0].reset();
                                    $('#roleStudent').prop('checked', true);
                                    toggleRoleFields();
                                });
                                // Trigger backend processing
                                $.ajax({
                                    url: '../actions/backend_process.php',
                                    type: 'POST',
                                    data: { participant_id: response.participant_id },
                                    success: function(backendResponse) {
                                        if (backendResponse.status !== 'success') {
                                            console.error('Backend processing failed:', backendResponse.message);
                                        }
                                    },
                                    error: function() {
                                        console.error('Error triggering backend processing.');
                                    }
                                });
                            }, 2000);
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            });

            // Handle import form submission
            let pollingInterval;
            $('#importForm').on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);
                formData.append('event_name', '<?php echo htmlspecialchars($event_name); ?>');
                $('#importModal').modal('hide');
                $('#processingModal').modal('show');
                $('#processingTableBody').empty();
                $('#progressPercentage').text('0%');
                $('#progressBar').css('width', '0%').attr('aria-valuenow', 0);
                $('#importSummary').hide();
                $('#actionButtons').hide();
                $('#successCount').text('0');
                $('#failedCount').text('0');

                $.ajax({
                    url: '../actions/import_excel.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        if (response.status === 'error') {
                            clearInterval(pollingInterval);
                            $('#processingModal').modal('hide');
                            alert(response.message);
                            return;
                        }
                        // Start polling for progress
                        pollingInterval = setInterval(function() {
                            $.ajax({
                                url: '../actions/import_excel.php?action=progress',
                                type: 'GET',
                                dataType: 'json',
                                success: function(progress) {
                                    if (progress.status === 'processing' || (progress.status === 'complete' && progress.current_row < progress.total_rows)) {
                                        let percentage = Math.round((progress.current_row / progress.total_rows) * 100);
                                        $('#progressPercentage').text(percentage + '%');
                                        $('#progressBar').css('width', percentage + '%').attr('aria-valuenow', percentage);
                                        $('#successCount').text(progress.successful_entries);
                                        $('#failedCount').text(progress.failed_entries);
                                        $('#importSummary').show();
                                        let tbody = $('#processingTableBody');
                                        tbody.empty();
                                        progress.rows.forEach(function(row) {
                                            let rowClass = row.status === 'success' ? 'success-row' : 'failed-row';
                                            tbody.append(`
                                                <tr class="${rowClass}">
                                                    <td>${row.data.Name || ''}</td>
                                                    <td>${row.data.Email || ''}</td>
                                                    <td>${row.data['Course Name'] || ''}</td>
                                                    <td>${row.data['Department Name'] || ''}</td>
                                                    <td>${row.data['College Name'] || 'N/A'}</td>
                                                    <td>${row.status === 'success' ? 'Inserted' : row.error}</td>
                                                </tr>
                                            `);
                                        });
                                    } else if (progress.status === 'complete' && progress.current_row >= progress.total_rows) {
                                        clearInterval(pollingInterval);
                                        let percentage = 100;
                                        $('#progressPercentage').text(percentage + '%');
                                        $('#progressBar').css('width', percentage + '%').attr('aria-valuenow', percentage);
                                        $('#successCount').text(progress.successful_entries);
                                        $('#failedCount').text(progress.failed_entries);
                                        $('#importSummary').show();
                                        $('#actionButtons').show();
                                        let tbody = $('#processingTableBody');
                                        tbody.empty();
                                        progress.rows.forEach(function(row) {
                                            let rowClass = row.status === 'success' ? 'success-row' : 'failed-row';
                                            tbody.append(`
                                                <tr class="${rowClass}">
                                                    <td>${row.data.Name || ''}</td>
                                                    <td>${row.data.Email || ''}</td>
                                                    <td>${row.data['Course Name'] || ''}</td>
                                                    <td>${row.data['Department Name'] || ''}</td>
                                                    <td>${row.data['College Name'] || 'N/A'}</td>
                                                    <td>${row.status === 'success' ? 'Inserted' : row.error}</td>
                                                </tr>
                                            `);
                                        });
                                    }
                                },
                                error: function() {
                                    clearInterval(pollingInterval);
                                    $('#processingModal').modal('hide');
                                    alert('Error fetching progress.');
                                }
                            });
                        }, 500);
                    },
                    error: function() {
                        $('#processingModal').modal('hide');
                        alert('Error initiating import.');
                    }
                });
            });

            // Handle Accept button
            $('#acceptButton').on('click', function() {
                $.ajax({
                    url: '../actions/import_excel.php?action=accept',
                    type: 'POST',
                    data: { event_name: '<?php echo htmlspecialchars($event_name); ?>' },
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#processingModal').modal('hide');
                            $('#successMessage').text(response.message);
                            $('#successAlert').show();
                            setTimeout(function() {
                                $('#successAlert').fadeOut('slow');
                                // Trigger bulk backend processing
                                if (response.first_id && response.last_id) {
                                    $.ajax({
                                        url: '../actions/bulk_backend_process.php',
                                        type: 'POST',
                                        data: {
                                            first_id: response.first_id,
                                            last_id: response.last_id,
                                            event_name: '<?php echo htmlspecialchars($event_name); ?>'
                                        },
                                        success: function(backendResponse) {
                                            if (backendResponse.status !== 'success') {
                                                console.error('Bulk backend processing failed:', backendResponse.message);
                                            }
                                        },
                                        error: function() {
                                            console.error('Error triggering bulk backend processing.');
                                        }
                                    });
                                }
                            }, 2000);
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error accepting entries.');
                    }
                });
            });

            // Handle Decline button
            $('#declineButton').on('click', function() {
                $.ajax({
                    url: '../actions/import_excel.php?action=decline',
                    type: 'POST',
                    data: { event_name: '<?php echo htmlspecialchars($event_name); ?>' },
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#processingModal').modal('hide');
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error declining entries.');
                    }
                });
            });

            // Prevent modal close during processing
            $('#closeProcessingModal').on('click', function() {
                if ($('#actionButtons').is(':visible')) {
                    $('#processingModal').modal('hide');
                }
            });
        });
    </script>
</body>
</html>