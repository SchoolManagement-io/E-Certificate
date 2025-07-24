<?php
include '../config.php';
include '../functions.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ?page=management_login");
    exit();
}

$conn = getDatabaseConnection();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT institution_name FROM management WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$institution_name = $result->fetch_assoc()['institution_name'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Management Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: url('../assets/dash_bg.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            width: 100%;
            max-width: 1200px;
            padding: 15px;
            padding-top: 60px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            width: 100%;
            padding: 0 70px;
        }
        .logout-btn {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1000;
        }
        .logout-btn a {
            text-decoration: none;
        }
        .card {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #20c997;
            border-radius: 10px;
            color: #000;
            margin-bottom: 20px;
            height: auto;
            min-height: 250px;
            display: flex;
            flex-direction: column;
        }
        .card-header {
            background: #20c997;
            color: #fff;
            font-weight: bold;
        }
        .card-body {
            flex-grow: 1;
            overflow-y: auto;
        }
        .btn, a {
            text-decoration: none !important;
        }
        .btn-primary {
            background-color: #ffc107;
            border-color: #ffc107;
        }
        .btn-primary:hover {
            background-color: #ffca2c;
            border-color: #ffca2c;
        }
        .table-responsive {
            max-height: 300px;
            overflow-y: auto;
            overflow-x: auto;
        }
        .table {
            width: 100%;
            min-width: 1200px;
        }
        .table th, .table td {
            white-space: nowrap;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input {
            display: none;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
        }
        input:checked + .slider {
            background-color: #20c997;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .slider.round {
            border-radius: 34px;
        }
        .slider.round:before {
            border-radius: 50%;
        }
        .modal-content {
            background: rgba(255, 255, 255, 0.95);
        }
        .modal-title {
            color: #20c997;
            font-weight: bold;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .form-label, .form-check-label {
            color: #000;
        }
        .filter-row .form-control, .filter-row .form-select, .filter-row .btn {
            flex: 1 1 100%;
        }
        .export-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        .export-checkboxes .form-check {
            flex: 1 1 100%;
            min-width: 120px;
        }
        @media (min-width: 576px) {
            .filter-row .form-control {
                flex: 2 1 50%;
            }
            .filter-row .form-select {
                flex: 1 1 30%;
            }
            .filter-row .btn {
                flex: 0 1 auto;
            }
            .export-checkboxes .form-check {
                flex: 1 1 45%;
            }
        }
        @media (min-width: 768px) {
            .export-checkboxes .form-check {
                flex: 1 1 30%;
            }
        }
        @media (min-width: 992px) {
            .export-checkboxes .form-check {
                flex: 1 1 20%;
            }
        }
        @media (max-width: 768px) {
            .card {
                min-height: 200px;
            }
            .table th, .table td {
                font-size: 0.9rem;
            }
            .container {
                padding-top: 50px;
            }
            .header {
                padding: 0 60px;
            }
            .logout-btn {
                top: 10px;
                right: 10px;
            }
        }
        @media (max-width: 576px) {
            .card {
                min-height: 150px;
            }
            .table th, .table td {
                font-size: 0.8rem;
            }
            .container {
                padding: 10px;
                padding-top: 70px;
            }
            .header {
                padding: 0 50px;
            }
            .logout-btn {
                top: 10px;
                right: 10px;
            }
        }
        .export-table-responsive {
            max-height: 400px;
            overflow-y: auto;
            overflow-x: auto;
        }
        .export-table {
            width: 100%;
            min-width: 1000px;
        }
    </style>
</head>
<body>
    <div class="logout-btn">
        <a href="../actions/logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="container">
        <div class="header">
            <h1 style="color:rgb(0, 0, 0); font-weight: bold;"><?php echo htmlspecialchars($institution_name); ?></h1>
            <h2 style="color:rgb(0, 0, 0); font-weight: bold;">Management Dashboard</h2>
        </div>
        <!-- Card 1: Upload Template -->
        <div class="card">
            <div class="card-header">Upload Template <i class="fas fa-upload"></i></div>
            <div class="card-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Event Name</label>
                        <input type="text" class="form-control" name="event_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template File (.pptx)</label>
                        <input type="file" class="form-control" name="template_file" accept=".pptx" required>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload</button>
                </form>
            </div>
        </div>
        <!-- Card 2: Participants List -->
        <div class="card">
            <div class="card-header">Participants List <i class="fas fa-users"></i></div>
            <div class="card-body">
                <div class="mb-3 filter-row">
                    <input type="text" class="form-control" id="filterText" placeholder="Filter by Name, Email, College">
                    <select class="form-select" id="eventFilter">
                        <option value="">All Events</option>
                        <?php
                        $query = "SELECT DISTINCT event_name FROM templates";
                        $result = $conn->query($query);
                        if ($result) {
                            while ($event = $result->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($event['event_name']) . "'>" . htmlspecialchars($event['event_name']) . "</option>";
                            }
                            $result->free();
                        } else {
                            echo "<option value=''>Error loading events</option>";
                        }
                        $conn->close();
                        ?>
                    </select>
                    <button class="btn btn-primary" id="exportBtn" data-bs-toggle="modal" data-bs-target="#exportModal">Export</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped" id="participantsTable">
                        <thead>
                            <tr>
                                <th>S.No.</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Course/Department</th>
                                <th>College</th>
                                <th>Event</th>
                                <th>Promo</th>
                                <th>Certificate Ref.</th>
                                <th>QR Code</th>
                                <th>Certificate File</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Card 3: Template List -->
        <div class="card">
            <div class="card-header">Template List <i class="fas fa-file-powerpoint"></i></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="templateTable">
                        <thead>
                            <tr>
                                <th>S.No.</th>
                                <th>Event Name</th>
                                <th>Template File</th>
                                <th>URL</th>
                                <th>Visibility</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">Upload Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <button type="button" class="btn btn-primary mb-3" id="modalUploadBtn">Upload</button>
                    <div class="card">
                        <div class="card-header">Template Requirements</div>
                        <div class="card-body">
                            <ul class="list-group">
                                <li class="list-group-item">File must have .ppt or .pptx extension.</li>
                                <li class="list-group-item">Mandatory placeholders (must be included in the template file as text and exactly as shown):
                                    <ul>
                                        <li><strong>{PARTICIPANT_NAME}</strong></li>
                                        <li><strong>{QR_CODE}</strong></li>
                                        <li><strong>{CERTIFICATE_REF}</strong></li>
                                    </ul>
                                </li>
                                <li class="list-group-item">Optional placeholders:
                                    <ul>
                                        <li>{COURSE_NAME}</li>
                                        <li>{DEPARTMENT_NAME}</li>
                                        <li>{COLLEGE_NAME}</li>
                                        <li>{EVENT_NAME}</li>
                                    </ul>
                                </li>
                                <li class="list-group-item">For best results, follow these guidelines:
                                    <ul>
                                        <li>Certificate design has been exported as a PNG.</li>
                                        <li>PNG is inserted inside the slide.</li>
                                        <li>Placeholders are placed above the PNG layer.</li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrModalLabel">QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="qrImage" src="" alt="QR Code" style="max-width: 100%;">
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Participant Modal -->
    <div class="modal fade" id="editParticipantModal" tabindex="-1" aria-labelledby="editParticipantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editParticipantModalLabel">Edit Participant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editParticipantForm" enctype="multipart/form-data">
                        <input type="hidden" name="participant_id" id="editParticipantId">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <div class="role-selection">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="editRoleStudent" value="Student">
                                    <label class="form-check-label" for="editRoleStudent">Student</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" id="editRoleFaculty" value="Faculty">
                                    <label class="form-check-label" for="editRoleFaculty">Faculty</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3" id="editCourseNameField">
                            <label class="form-label">Course Name (Optional)</label>
                            <input type="text" class="form-control" name="course_name" id="editCourseName">
                        </div>
                        <div class="mb-3" id="editDepartmentNameField" style="display: none;">
                            <label class="form-label">Department Name (Optional)</label>
                            <input type="text" class="form-control" name="department_name" id="editDepartmentName">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">College Name (Optional)</label>
                            <input type="text" class="form-control" name="college_name" id="editCollegeName">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Certificate File (Optional, .ppt, .pptx, or .pdf)</label>
                            <input type="file" class="form-control" name="certificate_file" id="editCertificateFile" accept=".ppt,.pptx,.pdf">
                            <div id="currentCertificate" class="mt-2" style="display: none;">
                                <span class="form-label">Current File: </span>
                                <a href="#" id="currentCertificateLink" target="_blank" download></a>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="updateParticipantBtn">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Select Columns to Export</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="export-checkboxes">
                        <div class="form-check">
                            <input class="form-check-input export-column" type="checkbox" id="colSNo" value="sno" checked>
                            <label class="form-check-label" for="colSNo">S.No.</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input export-column" type="checkbox" id="colName" value="name" checked>
                            <label class="form-check-label" for="colName">Name</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input export-column" type="checkbox" id="colEmail" value="email" checked>
                            <label class="form-check-label" for="colEmail">Email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input export-column" type="checkbox" id="colCourseDept" value="course_dept" checked>
                            <label class="form-check-label" for="colCourseDept">Course/Department</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input export-column" type="checkbox" id="colCollege" value="college_name" checked>
                            <label class="form-check-label" for="colCollege">College</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input export-column" type="checkbox" id="colEvent" value="event_name" checked>
                            <label class="form-check-label" for="colEvent">Event</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input export-column" type="checkbox" id="colPromo" value="promo_code" checked>
                            <label class="form-check-label" for="colPromo">Promo</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input export-column" type="checkbox" id="colCertRef" value="certificate_ref" checked>
                            <label class="form-check-label" for="colCertRef">Certificate Ref.</label>
                        </div>
                    </div>
                    <div class="export-table-responsive">
                        <table class="table table-striped export-table" id="exportTable">
                            <thead>
                                <tr>
                                    <th class="col-sno">S.No.</th>
                                    <th class="col-name">Name</th>
                                    <th class="col-email">Email</th>
                                    <th class="col-course_dept">Course/Department</th>
                                    <th class="col-college_name">College</th>
                                    <th class="col-event_name">Event</th>
                                    <th class="col-promo_code">Promo</th>
                                    <th class="col-certificate_ref">Certificate Ref.</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="downloadExcelBtn">Download</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            function loadParticipants() {
                $.ajax({
                    url: '../actions/fetch_participants.php',
                    type: 'GET',
                    data: {
                        filter: $('#filterText').val(),
                        event_filter: $('#eventFilter').val()
                    },
                    success: function(data) {
                        let tbody = $('#participantsTable tbody');
                        tbody.empty();
                        $.each(data, function(index, participant) {
                            let courseDept = participant.course_name || participant.department_name || '';
                            let row = `<tr>
                                <td>${participant.sno}</td>
                                <td>${participant.name}</td>
                                <td>${participant.email}</td>
                                <td>${courseDept}</td>
                                <td>${participant.college_name}</td>
                                <td>${participant.event_name}</td>
                                <td>${participant.promo_code || ''}</td>
                                <td>${participant.certificate_ref || ''}</td>
                                <td><a href="#" class="qr-link" data-qr="../qrcodes/${participant.qr_codes}"><i class="fas fa-qrcode"></i></a></td>
                                <td><a href="../certificates/${participant.certificate_file}" download><i class="fas fa-download"></i></a></td>
                                <td>
                                    <button class="btn btn-primary btn-sm edit-participant" data-id="${participant.id}" data-name="${participant.name}" data-email="${participant.email}" data-course_name="${participant.course_name || ''}" data-department_name="${participant.department_name || ''}" data-college_name="${participant.college_name || 'N/A'}" data-certificate_file="${participant.certificate_file || ''}"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="btn btn-danger btn-sm delete-participant" data-id="${participant.id}">Delete</button>
                                </td>
                            </tr>`;
                            tbody.append(row);
                        });
                    }
                });
            }

            function loadTemplates() {
                $.ajax({
                    url: '../actions/fetch_template.php',
                    type: 'GET',
                    success: function(data) {
                        let tbody = $('#templateTable tbody');
                        tbody.empty();
                        $.each(data, function(index, template) {
                            let row = `<tr>
                                <td>${template.sno}</td>
                                <td>${template.event_name}</td>
                                <td><a href="../templates/${template.template_file}" download><i class="fas fa-download"></i> Template</a></td>
                                <td><a href="?page=event_registration&event_name=${template.event_name}" target="_blank"><i class="fas fa-arrow-right"></i> Visit</a></td>
                                <td>
                                    <label class="switch">
                                        <input type="checkbox" class="toggle-visibility" data-id="${template.id}" ${template.visibility ? 'checked' : ''}>
                                        <span class="slider round"></span>
                                    </label>
                                </td>
                                <td><button class="btn btn-danger btn-sm delete-template" data-id="${template.id}">Delete</button></td>
                            </tr>`;
                            tbody.append(row);
                        });
                    }
                });
            }

            function loadExportTable() {
                $.ajax({
                    url: '../actions/fetch_participants.php',
                    type: 'GET',
                    data: {
                        filter: $('#filterText').val(),
                        event_filter: $('#eventFilter').val()
                    },
                    success: function(data) {
                        let tbody = $('#exportTable tbody');
                        tbody.empty();
                        $.each(data, function(index, participant) {
                            let courseDept = participant.course_name || participant.department_name || '';
                            let row = `<tr>
                                <td class="col-sno">${index + 1}</td>
                                <td class="col-name">${participant.name}</td>
                                <td class="col-email">${participant.email}</td>
                                <td class="col-course_dept">${courseDept}</td>
                                <td class="col-college_name">${participant.college_name}</td>
                                <td class="col-event_name">${participant.event_name}</td>
                                <td class="col-promo_code">${participant.promo_code || ''}</td>
                                <td class="col-certificate_ref">${participant.certificate_ref || ''}</td>
                            </tr>`;
                            tbody.append(row);
                        });
                        updateExportTable(); // Ensure table is updated after loading data
                    }
                });
            }

            function updateExportTable() {
                // Show all columns initially
                $('#exportTable th, #exportTable td').show();
                // Hide columns based on unchecked checkboxes
                $('.export-column').each(function() {
                    let colClass = '.col-' + $(this).val();
                    if (!$(this).is(':checked')) {
                        $('#exportTable ' + colClass).hide();
                    }
                });
            }

            $('#modalUploadBtn').on('click', function() {
                let formData = new FormData($('#uploadForm')[0]);
                $.ajax({
                    url: '../actions/upload_template.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        alert(response.message);
                        if (response.status === 'success') {
                            loadTemplates();
                            $('#uploadForm')[0].reset();
                            $('#uploadModal').modal('hide');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Upload failed: ' + error);
                    }
                });
            });

            $('#filterText, #eventFilter').on('input change', function() {
                loadParticipants();
                if ($('#exportModal').hasClass('show')) {
                    loadExportTable();
                }
            });

            $('#exportModal').on('show.bs.modal', function() {
                $('.export-column').prop('checked', true); // Reset checkboxes to checked
                loadExportTable(); // Load table and apply visibility
            });

            $('.export-column').on('change', function() {
                updateExportTable();
            });

            $('#downloadExcelBtn').on('click', function() {
                let selectedColumns = [];
                $('.export-column:checked').each(function() {
                    selectedColumns.push($(this).val());
                });
                if (selectedColumns.length === 0) {
                    alert('Please select at least one column to export.');
                    return;
                }
                let filter = $('#filterText').val();
                let eventFilter = $('#eventFilter').val();
                let columns = selectedColumns.join(',');
                window.location.href = '../actions/export_excel.php?filter=' + encodeURIComponent(filter) + '&event_filter=' + encodeURIComponent(eventFilter) + '&columns=' + encodeURIComponent(columns);
                $('#exportModal').modal('hide');
            });

            $(document).on('click', '.delete-participant', function() {
                if (confirm('Are you sure?')) {
                    $.post('../actions/delete_participant.php', { id: $(this).data('id') }, function(response) {
                        alert(response.message);
                        loadParticipants();
                        if ($('#exportModal').hasClass('show')) {
                            loadExportTable();
                        }
                    });
                }
            });

            $(document).on('click', '.delete-template', function() {
                if (confirm('Are you sure?')) {
                    $.post('../actions/delete_template.php', { id: $(this).data('id') }, function(response) {
                        alert(response.message);
                        loadTemplates();
                    });
                }
            });

            $(document).on('change', '.toggle-visibility', function() {
                $.post('../actions/toggle_visibility.php', {
                    id: $(this).data('id'),
                    visibility: $(this).is(':checked') ? 1 : 0
                }, function(response) {
                    alert(response.message);
                });
            });
            $(document).on('click', '.qr-link', function(e) {
                e.preventDefault();
                let qrSrc = $(this).data('qr');
                $('#qrImage').attr('src', qrSrc);
                $('#qrModal').modal('show');
            });

            $(document).on('click', '.edit-participant', function() {
                let participant = {
                    id: $(this).data('id'),
                    name: $(this).data('name'),
                    email: $(this).data('email'),
                    course_name: $(this).data('course_name'),
                    department_name: $(this).data('department_name'),
                    college_name: $(this).data('college_name'),
                    certificate_file: $(this).data('certificate_file')
                };

                $('#editParticipantId').val(participant.id);
                $('#editName').val(participant.name);
                $('#editEmail').val(participant.email);
                $('#editCollegeName').val(participant.college_name === 'N/A' ? '' : participant.college_name);
                $('#editCertificateFile').val('');
                if (participant.certificate_file) {
                    $('#currentCertificateLink').text(participant.certificate_file).attr('href', '../certificates/' + participant.certificate_file);
                    $('#currentCertificate').show();
                } else {
                    $('#currentCertificate').hide();
                }

                if (participant.course_name) {
                    $('#editRoleStudent').prop('checked', true);
                    $('#editCourseNameField').show();
                    $('#editDepartmentNameField').hide();
                    $('#editCourseName').val(participant.course_name);
                    $('#editDepartmentName').val('');
                } else if (participant.department_name) {
                    $('#editRoleFaculty').prop('checked', true);
                    $('#editCourseNameField').hide();
                    $('#editDepartmentNameField').show();
                    $('#editDepartmentName').val(participant.department_name);
                    $('#editCourseName').val('');
                } else {
                    $('#editRoleStudent').prop('checked', true);
                    $('#editCourseNameField').show();
                    $('#editDepartmentNameField').hide();
                    $('#editCourseName').val('');
                    $('#editDepartmentName').val('');
                }

                $('#editParticipantModal').modal('show');
            });

            $('input[name="role"]').on('change', function() {
                if ($('#editRoleStudent').is(':checked')) {
                    $('#editCourseNameField').show();
                    $('#editDepartmentNameField').hide();
                    $('#editDepartmentName').val('');
                } else {
                    $('#editCourseNameField').hide();
                    $('#editDepartmentNameField').show();
                    $('#editCourseName').val('');
                }
            });

            $('#updateParticipantBtn').on('click', function() {
                let formData = new FormData($('#editParticipantForm')[0]);
                $.ajax({
                    url: '../actions/update_participant.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        alert(response.message);
                        if (response.status === 'success') {
                            loadParticipants();
                            if ($('#exportModal').hasClass('show')) {
                                loadExportTable();
                            }
                            $('#editParticipantModal').modal('hide');
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Update failed: ' + error);
                    }
                });
            });

            loadParticipants();
            loadTemplates();
        });
    </script>
</body>
</html>