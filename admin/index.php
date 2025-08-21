<?php
// /admin/index.php (관리자 로그인 페이지)
session_start();
require_once __DIR__ . '/../pdo.php';  // 상위 폴더 pdo.php

// 이미 로그인 중이면 /admin/admin.php 이동
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

// 관리자 비밀번호 (하드코딩 예시)
$ADMIN_PASSWORD = 'gshs2025!';

// 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputPassword = $_POST['admin_password'] ?? '';
    if ($inputPassword === $ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        // 로그인 성공 -> 관리자 페이지 이동
        header('Location: admin.php');
        exit;
    } else {
        // 로그인 실패
        $error_message = "비밀번호가 올바르지 않습니다.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>관리자 로그인</title>
    <!-- 파비콘 -->
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&family=Roboto:wght@400;500;700&display=swap');
        body { font-family: 'Noto Sans KR', sans-serif; }
        .title { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">
    <!-- 상단 메뉴바 (fixed X → 일반) -->
    <div class="bg-white shadow z-50">
        <div class="container mx-auto px-4 py-3">
            <!-- 좌측 문구: "경기과학고 멘토링 신청 관리" -->
            <h1 class="text-lg font-semibold text-gray-800">경기과학고 멘토링 신청 관리</h1>
        </div>
    </div>

    <!-- 페이지 메인 컨텐츠 -->
    <div class="flex-grow flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md mt-5">
            <!-- 로고 -->
            <div class="flex justify-center mb-4">
                <img src="../logo.png" alt="Logo" class="h-16">
            </div>

            <!-- 에러 메시지 -->
            <?php if (!empty($error_message)): ?>
              <div class="bg-red-100 text-red-600 p-2 rounded mb-4">
                <?php echo $error_message; ?>
              </div>
            <?php endif; ?>

            <!-- 로그인 폼 -->
            <form id="loginForm" method="POST" onsubmit="event.preventDefault(); showWarningModal();">
                <div class="mb-6">
                    <label for="admin_password" class="block text-sm font-medium text-gray-700">관리자 비밀번호</label>
                    <input 
                        type="password" 
                        id="admin_password" 
                        name="admin_password"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                               focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="비밀번호를 입력하세요"
                        required
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    로그인
                </button>
            </form>
        </div>
    </div>

    <!-- 경고 모달 -->
    <div 
        id="warningModal" 
        class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
    >
        <div class="bg-white p-6 rounded-lg w-full max-w-md mx-4">
            <div class="text-center">
                <h3 class="text-lg font-bold mb-4">⚠️ 경 고 ⚠️</h3>
                <p class="text-red-600 mb-4">
                    관리자 전용 페이지입니다.<br />
                    무단 접근 시 관련 규정에 따라 처벌받을 수 있습니다.
                </p>
                <button 
                    onclick="submitForm()"
                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700"
                >
                    충분히 이해했습니다
                </button>
            </div>
        </div>
    </div>

    <!-- 하단 문구 (sticky footer) -->
    <footer class="mt-auto text-center text-sm text-gray-500 py-2">
        © 2025 경기과학고등학교 학생회
        <br />
        Developed by 전교부회장 장민서
    </footer>

    <script>
        function showWarningModal() {
            document.getElementById('warningModal').classList.remove('hidden');
        }
        function submitForm() {
            document.getElementById('warningModal').classList.add('hidden');
            document.getElementById('loginForm').submit();
        }
        document.getElementById('warningModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('warningModal')) {
                document.getElementById('warningModal').classList.add('hidden');
            }
        });
    </script>
</body>
</html>
