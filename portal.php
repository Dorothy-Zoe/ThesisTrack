<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="images/book-icon.ico">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="CSS/portal.css">
    <title>ThesisTrack - CICT Portal</title>
    
</head>
<body>
  
    <div class="login-container">
        <div class="login-left">
            <div class="university-info">
                <div class="university-logo" ><a href="index.php"><img src="images/book-icon.png" alt="Logo" /></a></div>
                <div class="university-name">TAGUIG CITY UNIVERSITY</div>
                <div class="college-name">College of Information and Communication Technology</div>
                <div class="system-title">ThesisTrack</div>
                <div class="system-subtitle">Comprehensive Thesis Management System for BSCS & BSIS Students</div>
            </div>
        </div>
        
       


        <div class="login-right">
                    
            <div class="login-header">
                <h2>Welcome to CICT Portal</h2>
                <p>Select your role to access the thesis management system</p>
            </div>
            
            <div class="role-selection">
                <div class="role-card" onclick="selectRole('student')">
                    <div class="role-icon">
                    <i class="fas fa-graduation-cap"></i>
                    </div>

                    <div class="role-content">
                        <div class="role-title">Student</div>
                        <div class="role-description">Upload chapters, track progress, and receive feedback</div>
                    </div>
                    <div class="role-arrow">→</div>
                </div>
                
                <div class="role-card" onclick="selectRole('advisor')">
                    <div class="role-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                    </div>

                    <div class="role-content">
                        <div class="role-title">Subject Advisor</div>
                        <div class="role-description">Monitor groups, review chapters, and provide feedback</div>
                    </div>
                    <div class="role-arrow">→</div>
                </div>
                
                <div class="role-card" onclick="selectRole('coordinator')">
                    <div class="role-icon">
                    <i class="fas fa-user-tie"></i>
                    </div>

                    <div class="role-content">
                        <div class="role-title">Research Coordinator</div>
                        <div class="role-description">Oversee all sections and monitor system-wide progress</div>
                    </div>
                    <div class="role-arrow">→</div>
                </div>
            </div>
            
          
        </div>
    </div>

    
  <!-- <div class="footer-links">
                
                <a href="#" class="footer-link" onclick="openRegistration('advisor')">Advisor Registration</a>
            </div> -->
    <!-- Advisor Registration Modal
    <div id="advisorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('advisorModal')">&times;</span>
                <h3>Subject Advisor Registration</h3>
                <p>Register as a CICT Faculty Member</p>
            </div>
            <div class="modal-body">
                <div id="advisorAlert"></div>
                <form id="advisorForm" method="POST" action="register.php">
                    <input type="hidden" name="role" value="advisor">
                    
                    <div class="form-group">
                        <label for="advisorName">Full Name *</label>
                        <input type="text" id="advisorName" name="name" required placeholder="Dr./Prof. Full Name">
                    </div>
                    
                    <div class="form-group">
                        <label for="employeeId">Employee ID *</label>
                        <input type="text" id="employeeId" name="employee_id" required placeholder="e.g., EMP-2024-001">
                    </div>
                    
                    <div class="form-group">
                        <label for="advisorEmail">Email Address *</label>
                        <input type="email" id="advisorEmail" name="email" required placeholder="faculty.email@cict.edu">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="advisorCourse">Course Specialization *</label>
                            <select id="advisorCourse" name="course" required>
                                <option value="">Select Course</option>
                                <option value="BSCS">BS Computer Science</option>
                                <option value="BSIS">BS Information Systems</option>
                                <option value="BOTH">Both BSCS & BSIS</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="advisorYear">Year Level Handled *</label>
                            <select id="advisorYear" name="year_handled" required>
                                <option value="">Select Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                                <option value="BOTH">Both 3rd & 4th Year</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="sectionsHandled">Sections Handled *</label>
                        <input type="text" id="sectionsHandled" name="sections_handled" required placeholder="e.g., BSCS-4A, BSCS-4B">
                    </div>
                    
                    <div class="form-group">
                        <label for="advisorPassword">Password *</label>
                        <input type="password" id="advisorPassword" name="password" required placeholder="Enter password">
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <input type="text" id="department" name="department" value="College of Information and Communication Technology" readonly>
                    </div>
                    
                    <button type="submit" class="btn-primary">Register as Advisor</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('advisorModal')">Cancel</button>
                </form>
            </div>
        </div>
    </div> -->

    <script src="JS/portal.js"></script>
</body>
</html>
