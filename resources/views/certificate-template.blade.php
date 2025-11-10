<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Certificate of Completion</title>
    <style>
        @page {
            margin: 0;
            size: A4 landscape;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Georgia', serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .certificate-container {
            width: 100%;
            height: 100vh;
            position: relative;
            background: white;
            padding: 60px;
            box-sizing: border-box;
        }

        .certificate-border {
            border: 15px solid #667eea;
            border-image: linear-gradient(45deg, #667eea, #764ba2) 1;
            padding: 40px;
            height: 100%;
            position: relative;
        }

        .certificate-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo {
            max-width: 150px;
            margin-bottom: 20px;
        }

        .certificate-title {
            font-size: 48px;
            color: #667eea;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 8px;
            margin: 20px 0;
        }

        .certificate-subtitle {
            font-size: 18px;
            color: #666;
            margin: 10px 0;
        }

        .certificate-body {
            text-align: center;
            margin: 60px 0;
        }

        .presented-to {
            font-size: 24px;
            color: #666;
            margin-bottom: 15px;
        }

        .student-name {
            font-size: 56px;
            color: #333;
            font-weight: bold;
            margin: 20px 0;
            font-family: 'Brush Script MT', cursive;
        }

        .completion-text {
            font-size: 20px;
            color: #666;
            line-height: 1.6;
            margin: 30px auto;
            max-width: 700px;
        }

        .course-title {
            font-size: 32px;
            color: #667eea;
            font-weight: bold;
            margin: 20px 0;
        }

        .certificate-footer {
            position: absolute;
            bottom: 40px;
            left: 40px;
            right: 40px;
            display: flex;
            justify-content: space-around;
            margin-top: 60px;
        }

        .signature-block {
            text-align: center;
            width: 250px;
        }

        .signature-line {
            border-top: 2px solid #333;
            margin: 20px 0 10px;
        }

        .signature-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .signature-name {
            font-size: 18px;
            color: #333;
            font-weight: bold;
            margin-top: 5px;
        }

        .certificate-id {
            position: absolute;
            bottom: 20px;
            right: 40px;
            font-size: 12px;
            color: #999;
        }

        .decorative-line {
            width: 200px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            margin: 20px auto;
        }
    </style>
</head>

<body>
    <div class="certificate-container">
        <div class="certificate-border">
            <div class="certificate-header">
                @if ($tenant_logo)
                    <img src="{{ $tenant_logo }}" alt="Logo" class="logo">
                @endif
                <h1 class="certificate-title">Certificate</h1>
                <div class="decorative-line"></div>
                <p class="certificate-subtitle">of Completion</p>
            </div>

            <div class="certificate-body">
                <p class="presented-to">This is to certify that</p>

                <div class="student-name">{{ $student_name }}</div>

                <p class="completion-text">
                    has successfully completed the course
                </p>

                <div class="course-title">{{ $course_title }}</div>

                <p class="completion-text">
                    on {{ $completion_date }}<br>
                    Duration: {{ $duration }} | Rating: {{ number_format($rating, 1) }}/5.0
                </p>
            </div>

            <div class="certificate-footer">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">Instructor</div>
                    <div class="signature-name">{{ $instructor_name }}</div>
                </div>

                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">Academy</div>
                    <div class="signature-name">{{ $tenant_name }}</div>
                </div>
            </div>

            <div class="certificate-id">
                Certificate ID: {{ $certificate_id }}
            </div>
        </div>
    </div>
</body>

</html>
