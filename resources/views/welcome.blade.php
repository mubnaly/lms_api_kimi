<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS API Testing Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <style>
        .endpoint-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .endpoint-card {
            transition: all 0.3s ease;
        }

        .method-badge {
            font-size: 0.7rem;
            font-weight: 700;
        }

        pre {
            font-family: 'Courier New', monospace;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-2xl">
        <div class="container mx-auto px-6 py-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold mb-2">🎓 LMS API Testing</h1>
                    <p class="text-blue-100">Professional Multi-Tenant E-Learning Platform</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-blue-100">API Version</div>
                    <div class="text-2xl font-bold">v1.0.0</div>
                </div>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-6 py-8">
        <!-- Configuration Panel -->
        <div class="bg-gray-800 rounded-xl shadow-2xl p-6 mb-8 border border-gray-700">
            <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                <span class="mr-3">⚙️</span> Configuration
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">API Base URL</label>
                    <input type="text" id="apiUrl" value="http://localhost:8000"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Tenant ID (Optional)</label>
                    <input type="text" id="tenantId" placeholder="tenant_uuid"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Environment</label>
                    <select id="environment"
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
                        <option value="local">Local</option>
                        <option value="staging">Staging</option>
                        <option value="production">Production</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Authentication Panel -->
        <div class="bg-gray-800 rounded-xl shadow-2xl p-6 mb-8 border border-gray-700">
            <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                <span class="mr-3">🔐</span> Authentication
            </h2>

            <div id="loginForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                        <input type="email" id="loginEmail" value="admin@lms.test"
                            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                        <input type="password" id="loginPassword" value="password"
                            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-end">
                        <button onclick="login()"
                            class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white px-6 py-3 rounded-lg hover:from-blue-600 hover:to-blue-700 transition font-medium">
                            Login
                        </button>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button onclick="quickLogin('admin')"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm">Admin</button>
                    <button onclick="quickLogin('instructor')"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">Instructor</button>
                    <button onclick="quickLogin('student')"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">Student</button>
                </div>
            </div>

            <div id="authStatus" class="mt-4 hidden">
                <div class="bg-green-900/50 border border-green-500 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-400 font-medium mb-2">✅ Authenticated</p>
                            <p class="text-xs text-gray-400">Token:</p>
                            <code id="tokenDisplay" class="text-xs text-green-300 break-all"></code>
                        </div>
                        <button onclick="logout()"
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-sm">
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Endpoints Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
            <!-- Public Endpoints -->
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6 border border-gray-700">
                <h3 class="text-xl font-bold text-green-400 mb-4 flex items-center">
                    <span class="mr-2">📖</span> Public Endpoints
                </h3>
                <div class="space-y-2">
                    <button onclick="testEndpoint('GET', '/health')"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /health
                    </button>
                    <button onclick="testEndpoint('GET', '/api/v1/courses')"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /courses
                    </button>
                    <button onclick="testEndpoint('GET', '/api/v1/categories')"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /categories
                    </button>
                    <button onclick="testEndpoint('GET', '/api/v1/instructors')"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /instructors
                    </button>
                </div>
            </div>

            <!-- Auth Endpoints -->
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6 border border-gray-700">
                <h3 class="text-xl font-bold text-blue-400 mb-4 flex items-center">
                    <span class="mr-2">🔒</span> Authenticated
                </h3>
                <div class="space-y-2">
                    <button onclick="testEndpoint('GET', '/api/v1/profile', true)"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /profile
                    </button>
                    <button onclick="testEndpoint('GET', '/api/v1/my-enrollments', true)"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /my-enrollments
                    </button>
                    <button onclick="testEndpoint('GET', '/api/v1/wishlist', true)"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /wishlist
                    </button>
                </div>
            </div>

            <!-- Admin Endpoints -->
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6 border border-gray-700">
                <h3 class="text-xl font-bold text-purple-400 mb-4 flex items-center">
                    <span class="mr-2">👑</span> Admin Only
                </h3>
                <div class="space-y-2">
                    <button onclick="testEndpoint('GET', '/api/v1/admin/analytics/overview', true)"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /admin/analytics
                    </button>
                    <button onclick="testEndpoint('GET', '/api/v1/admin/pending-instructors', true)"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /pending-instructors
                    </button>
                    <button onclick="testEndpoint('GET', '/api/v1/admin/pending-courses', true)"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-blue-500 px-2 py-1 rounded mr-2">GET</span> /pending-courses
                    </button>
                </div>
            </div>

            <!-- Instructor Endpoints -->
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6 border border-gray-700">
                <h3 class="text-xl font-bold text-yellow-400 mb-4 flex items-center">
                    <span class="mr-2">👨‍🏫</span> Instructor
                </h3>
                <div class="space-y-2">
                    <button onclick="createCourse()"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-green-500 px-2 py-1 rounded mr-2">POST</span> Create Course
                    </button>
                    <button onclick="createSection()"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-green-500 px-2 py-1 rounded mr-2">POST</span> Create Section
                    </button>
                    <button onclick="createLesson()"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-green-500 px-2 py-1 rounded mr-2">POST</span> Create Lesson
                    </button>
                </div>
            </div>

            <!-- Student Actions -->
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6 border border-gray-700">
                <h3 class="text-xl font-bold text-pink-400 mb-4 flex items-center">
                    <span class="mr-2">🎓</span> Student Actions
                </h3>
                <div class="space-y-2">
                    <button onclick="enrollCourse()"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-green-500 px-2 py-1 rounded mr-2">POST</span> Enroll in Course
                    </button>
                    <button
                        onclick="testEndpoint('POST', '/api/v1/courses/complete-laravel-11-mastery/wishlist', true)"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-green-500 px-2 py-1 rounded mr-2">POST</span> Toggle Wishlist
                    </button>
                    <button onclick="submitReview()"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-white">
                        <span class="method-badge bg-green-500 px-2 py-1 rounded mr-2">POST</span> Submit Review
                    </button>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-gray-800 rounded-xl shadow-2xl p-6 border border-gray-700">
                <h3 class="text-xl font-bold text-orange-400 mb-4 flex items-center">
                    <span class="mr-2">⚡</span> Quick Tests
                </h3>
                <div class="space-y-2">
                    <button onclick="runFullFlow()"
                        class="endpoint-card w-full text-left px-4 py-3 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 rounded-lg transition text-white font-medium">
                        🚀 Run Full Flow Test
                    </button>
                    <button onclick="clearAllData()"
                        class="endpoint-card w-full text-left px-4 py-3 bg-red-600 hover:bg-red-700 rounded-lg transition text-white">
                        🗑️ Clear Test Data
                    </button>
                    <button onclick="exportResults()"
                        class="endpoint-card w-full text-left px-4 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg transition text-white">
                        📥 Export Results
                    </button>
                </div>
            </div>
        </div>

        <!-- Response Panel -->
        <div class="bg-gray-800 rounded-xl shadow-2xl p-6 border border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-white flex items-center">
                    <span class="mr-3">📝</span> Response
                </h3>
                <div id="responseStatus" class="text-lg font-bold"></div>
            </div>

            <div class="bg-gray-900 rounded-lg p-6 border border-gray-700">
                <div id="responseMetadata" class="mb-4 grid grid-cols-3 gap-4 text-sm"></div>
                <pre id="responseBody" class="text-green-400 overflow-x-auto max-h-96 overflow-y-auto text-sm">
Waiting for API request...</pre>
            </div>
        </div>
    </div>

    <script>
        let authToken = localStorage.getItem('auth_token');
        let testResults = [];

        if (authToken) showAuthStatus();

        function getApiUrl() {
            return document.getElementById('apiUrl').value;
        }

        function getHeaders(requiresAuth = false) {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };

            const tenantId = document.getElementById('tenantId').value;
            if (tenantId) {
                headers['X-Tenant-ID'] = tenantId;
            }

            if (requiresAuth && authToken) {
                headers['Authorization'] = `Bearer ${authToken}`;
            }

            return headers;
        }

        function quickLogin(role) {
            const credentials = {
                'admin': {
                    email: 'admin@lms.test',
                    password: 'password'
                },
                'instructor': {
                    email: 'instructor@lms.test',
                    password: 'password'
                },
                'student': {
                    email: 'student@lms.test',
                    password: 'password'
                }
            };

            document.getElementById('loginEmail').value = credentials[role].email;
            document.getElementById('loginPassword').value = credentials[role].password;
            login();
        }

        async function login() {
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;

            try {
                const response = await axios.post(`${getApiUrl()}/api/v1/login`, {
                    email,
                    password
                }, {
                    headers: getHeaders()
                });

                authToken = response.data.access_token;
                localStorage.setItem('auth_token', authToken);
                showAuthStatus();
                displayResponse(response.status, response.data, response.headers);
            } catch (error) {
                displayResponse(
                    error.response?.status || 500,
                    error.response?.data || {
                        error: error.message
                    },
                    error.response?.headers
                );
            }
        }

        function logout() {
            authToken = null;
            localStorage.removeItem('auth_token');
            document.getElementById('authStatus').classList.add('hidden');
            document.getElementById('loginForm').classList.remove('hidden');
        }

        function showAuthStatus() {
            document.getElementById('authStatus').classList.remove('hidden');
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('tokenDisplay').textContent = authToken.substring(0, 50) + '...';
        }

        async function testEndpoint(method, endpoint, requiresAuth = false) {
            const config = {
                method,
                url: `${getApiUrl()}${endpoint}`,
                headers: getHeaders(requiresAuth),
            };

            try {
                const response = await axios(config);
                displayResponse(response.status, response.data, response.headers);
                logTestResult(endpoint, true, response.status);
            } catch (error) {
                displayResponse(
                    error.response?.status || 500,
                    error.response?.data || {
                        error: error.message
                    },
                    error.response?.headers
                );
                logTestResult(endpoint, false, error.response?.status);
            }
        }

        async function createCourse() {
            if (!authToken) {
                alert('Please login as instructor first');
                return;
            }

            const courseData = {
                title: 'Test Course ' + Date.now(),
                subtitle: 'A test course',
                description: 'This is a comprehensive test course description',
                level: 'beginner',
                language: 'arabic',
                price: 99.99,
                category_id: 1,
                requirements: ['Basic knowledge'],
                outcomes: ['Learn testing', 'Master APIs']
            };

            try {
                const response = await axios.post(
                    `${getApiUrl()}/api/v1/courses`,
                    courseData, {
                        headers: getHeaders(true)
                    }
                );
                displayResponse(response.status, response.data, response.headers);
            } catch (error) {
                displayResponse(
                    error.response?.status || 500,
                    error.response?.data || {
                        error: error.message
                    },
                    error.response?.headers
                );
            }
        }

        async function enrollCourse() {
            if (!authToken) {
                alert('Please login first');
                return;
            }

            const slug = prompt('Enter course slug:', 'complete-laravel-11-mastery');
            if (!slug) return;

            try {
                const response = await axios.post(
                    `${getApiUrl()}/api/v1/courses/${slug}/enroll`, {}, {
                        headers: getHeaders(true)
                    }
                );
                displayResponse(response.status, response.data, response.headers);
            } catch (error) {
                displayResponse(
                    error.response?.status || 500,
                    error.response?.data || {
                        error: error.message
                    },
                    error.response?.headers
                );
            }
        }

        function displayResponse(status, data, headers = {}) {
            const statusDiv = document.getElementById('responseStatus');
            const metadataDiv = document.getElementById('responseMetadata');
            const bodyPre = document.getElementById('responseBody');

            const statusColor = status >= 200 && status < 300 ? 'text-green-400' : 'text-red-400';
            statusDiv.innerHTML = `<span class="${statusColor}">${status}</span>`;

            const responseTime = headers['x-response-time'] || 'N/A';
            const contentLength = headers['content-length'] || 'N/A';

            metadataDiv.innerHTML = `
                <div class="text-gray-400">
                    <span class="text-gray-500">Response Time:</span> ${responseTime}
                </div>
                <div class="text-gray-400">
                    <span class="text-gray-500">Content Length:</span> ${contentLength}
                </div>
                <div class="text-gray-400">
                    <span class="text-gray-500">Status:</span>
                    <span class="${statusColor}">${status >= 200 && status < 300 ? 'Success' : 'Error'}</span>
                </div>
            `;

            bodyPre.textContent = JSON.stringify(data, null, 2);
        }

        function logTestResult(endpoint, success, status) {
            testResults.push({
                endpoint,
                success,
                status,
                timestamp: new Date().toISOString()
            });
        }

        async function runFullFlow() {
            alert('Running full flow test...');
            // Implement full flow test
        }

        function exportResults() {
            const blob = new Blob([JSON.stringify(testResults, null, 2)], {
                type: 'application/json'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `api-test-results-${Date.now()}.json`;
            a.click();
        }
    </script>
</body>

</html>
