<?php
// /admin/admin.php
session_start();
require_once __DIR__ . '/../pdo.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$msg = '';

// 로그아웃 처리
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 현재 시간
$now = new DateTime();

// ===== 멘티 신청 시간 설정 (apply_start, apply_end) =====
$sqlApplyStart = "SELECT config_value FROM system_config WHERE config_key='apply_start'";
$stmtApplyStart = $pdo->prepare($sqlApplyStart);
$stmtApplyStart->execute();
$applyStartValue = $stmtApplyStart->fetchColumn();

$sqlApplyEnd = "SELECT config_value FROM system_config WHERE config_key='apply_end'";
$stmtApplyEnd = $pdo->prepare($sqlApplyEnd);
$stmtApplyEnd->execute();
$applyEndValue = $stmtApplyEnd->fetchColumn();

$applyStart = !empty($applyStartValue) ? DateTime::createFromFormat('Y-m-d H:i:s', $applyStartValue) : null;
$applyEnd   = !empty($applyEndValue)   ? DateTime::createFromFormat('Y-m-d H:i:s', $applyEndValue)   : null;

$menteeStatus = ''; // 'not_started', 'open', 'closed'
if ($applyStart && $now < $applyStart) {
    $menteeStatus = 'not_started';
} elseif ($applyStart && $applyEnd && $now >= $applyStart && $now < $applyEnd) {
    $menteeStatus = 'open';
} elseif ($applyEnd && $now >= $applyEnd) {
    $menteeStatus = 'closed';
}

// ===== 멘토 등록 시간 설정 (mento_reg_start, mento_reg_end) =====
$sqlRegStart = "SELECT config_value FROM system_config WHERE config_key='mento_reg_start'";
$stmtRegStart = $pdo->prepare($sqlRegStart);
$stmtRegStart->execute();
$regStartValue = $stmtRegStart->fetchColumn();

$sqlRegEnd = "SELECT config_value FROM system_config WHERE config_key='mento_reg_end'";
$stmtRegEnd = $pdo->prepare($sqlRegEnd);
$stmtRegEnd->execute();
$regEndValue = $stmtRegEnd->fetchColumn();

$regStart = !empty($regStartValue) ? DateTime::createFromFormat('Y-m-d H:i:s', $regStartValue) : null;
$regEnd   = !empty($regEndValue)   ? DateTime::createFromFormat('Y-m-d H:i:s', $regEndValue)   : null;

$mentoRegStatus = ''; // 'not_started', 'open', 'closed'
if ($regStart && $now < $regStart) {
    $mentoRegStatus = 'not_started';
} elseif ($regStart && $regEnd && $now >= $regStart && $now < $regEnd) {
    $mentoRegStatus = 'open';
} elseif ($regEnd && $now >= $regEnd) {
    $mentoRegStatus = 'closed';
}

// ===== 멘티 신청 시간 수정 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_mentee') {
    $newStart = trim($_POST['apply_start'] ?? '');
    $newEnd   = trim($_POST['apply_end'] ?? '');
    if ($newStart !== '' && $newEnd !== '') {
        try {
            $dtStart = new DateTime($newStart);
            $dtEnd   = new DateTime($newEnd);
            $finalStart = $dtStart->format('Y-m-d H:i:s');
            $finalEnd   = $dtEnd->format('Y-m-d H:i:s');

            // 업데이트 apply_start
            $sql1 = "INSERT INTO system_config (config_key, config_value) VALUES ('apply_start', :val) ON DUPLICATE KEY UPDATE config_value = :val";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([':val' => $finalStart]);

            // 업데이트 apply_end
            $sql2 = "INSERT INTO system_config (config_key, config_value) VALUES ('apply_end', :val) ON DUPLICATE KEY UPDATE config_value = :val";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([':val' => $finalEnd]);

            header('Location: admin.php?msg=' . urlencode("멘티 신청 시간 수정 완료: 시작 {$finalStart}, 종료 {$finalEnd}"));
            exit;
        } catch (Exception $e) {
            header('Location: admin.php?msg=' . urlencode("멘티 신청 시간 수정 오류: " . $e->getMessage()));
            exit;
        }
    }
}

// ===== 멘토 등록 시간 수정 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_mento') {
    $newRegStart = trim($_POST['mento_reg_start'] ?? '');
    $newRegEnd   = trim($_POST['mento_reg_end'] ?? '');
    if ($newRegStart !== '' && $newRegEnd !== '') {
        try {
            $dtRegStart = new DateTime($newRegStart);
            $dtRegEnd   = new DateTime($newRegEnd);
            $finalRegStart = $dtRegStart->format('Y-m-d H:i:s');
            $finalRegEnd   = $dtRegEnd->format('Y-m-d H:i:s');

            // 업데이트 mento_reg_start
            $sql3 = "INSERT INTO system_config (config_key, config_value) VALUES ('mento_reg_start', :val) ON DUPLICATE KEY UPDATE config_value = :val";
            $stmt3 = $pdo->prepare($sql3);
            $stmt3->execute([':val' => $finalRegStart]);

            // 업데이트 mento_reg_end
            $sql4 = "INSERT INTO system_config (config_key, config_value) VALUES ('mento_reg_end', :val) ON DUPLICATE KEY UPDATE config_value = :val";
            $stmt4 = $pdo->prepare($sql4);
            $stmt4->execute([':val' => $finalRegEnd]);

            header('Location: admin.php?msg=' . urlencode("멘토 등록 시간 수정 완료: 시작 {$finalRegStart}, 종료 {$finalRegEnd}"));
            exit;
        } catch (Exception $e) {
            header('Location: admin.php?msg=' . urlencode("멘토 등록 시간 수정 오류: " . $e->getMessage()));
            exit;
        }
    }
}

// ===== 멘티 신청자 삭제 처리 =====
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    if ($deleteId > 0) {
        $chk = $pdo->prepare("SELECT apply_id FROM menti_apply WHERE apply_id = :id");
        $chk->execute([':id' => $deleteId]);
        if ($chk->fetch()) {
            $del = $pdo->prepare("DELETE FROM menti_apply WHERE apply_id = :id");
            $del->execute([':id' => $deleteId]);
            $msg = "신청ID {$deleteId}가 삭제되었습니다.";
        } else {
            $msg = "해당 신청을 찾을 수 없습니다.";
        }
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// ===== DB에서 멘티 신청 시간, 멘토 목록, 멘티 신청 내역 조회 =====
$stmtConf = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key='apply_start'");
$stmtConf->execute();
$applyStartValue = $stmtConf->fetchColumn();

$sqlMento = "SELECT mento_no, mento_name, mento_cv, menti_limit FROM mento_info ORDER BY mento_no ASC";
$mentoList = $pdo->query($sqlMento)->fetchAll(PDO::FETCH_ASSOC);

$sqlApply = "
    SELECT a.apply_id, a.mento_no, i.mento_name,
           a.student_id, a.student_name, a.student_phone,
           a.apply_time, a.apply_ip
    FROM menti_apply a
    LEFT JOIN mento_info i ON a.mento_no = i.mento_no
    ORDER BY a.apply_id DESC
";
$applyList = $pdo->query($sqlApply)->fetchAll(PDO::FETCH_ASSOC);

// 서버 시간 (JS용)
$serverTimeStr = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>관리자 페이지</title>
  <!-- 파비콘 -->
  <link rel="icon" href="../favicon.ico" type="image/x-icon">
  <!-- Tailwind CSS -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans', sans-serif; }
    input[type=datetime-local] { padding: 0.25rem; border: 1px solid #ccc; border-radius: 4px; }
  </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">
  <!-- 상단 메뉴바 -->
  <nav class="bg-white shadow mb-4">
    <div class="max-w-7xl mx-auto px-2 py-2 flex items-center justify-between">
      <!-- 좌측: 로고 + "멘토링 신청 관리" -->
      <div class="flex items-center space-x-2">
        <img src="../logo.png" alt="logo" class="h-8">
        <h1 class="text-xl font-bold">멘토링 신청 관리</h1>
      </div>
      <!-- 우측: 로그아웃 -->
      <div>
        <a href="?action=logout" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
          로그아웃
        </a>
      </div>
    </div>
  </nav>

  <!-- 컨테이너 -->
  <div class="flex-grow max-w-7xl mx-auto px-2">
    <?php if (!empty($msg)): ?>
      <div class="bg-green-100 text-green-700 p-2 rounded mb-4">
        <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <!-- [멘티 신청 시간 수정 폼] -->
    <section class="mb-8 bg-white p-4 rounded shadow">
      <h2 class="text-xl font-bold mb-2">멘티 신청 시간 설정</h2>
      <form method="POST" action="">
        <input type="hidden" name="action" value="update_mentee">
        <?php
        $menteeStartLocal = '';
        $menteeEndLocal = '';
        if (!empty($applyStartValue)) {
            $dt1 = DateTime::createFromFormat('Y-m-d H:i:s', $applyStartValue);
            if ($dt1) {
                $menteeStartLocal = $dt1->format('Y-m-d\TH:i');
            }
        }
        if (!empty($applyEndValue)) {
            $dt2 = DateTime::createFromFormat('Y-m-d H:i:s', $applyEndValue);
            if ($dt2) {
                $menteeEndLocal = $dt2->format('Y-m-d\TH:i');
            }
        }
        ?>
        <div class="flex items-center space-x-2 mb-2">
          <label class="font-medium">신청 시작 시각:</label>
          <input type="datetime-local" name="apply_start" required value="<?php echo htmlspecialchars($menteeStartLocal); ?>">
        </div>
        <div class="flex items-center space-x-2 mb-2">
          <label class="font-medium">신청 종료 시각:</label>
          <input type="datetime-local" name="apply_end" required value="<?php echo htmlspecialchars($menteeEndLocal); ?>">
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">저장하기</button>
      </form>
    </section>

    <!-- [멘토 등록 시간 수정 폼] -->
    <section class="mb-8 bg-white p-4 rounded shadow">
      <h2 class="text-xl font-bold mb-2">멘토 등록 시간 설정</h2>
      <form method="POST" action="">
        <input type="hidden" name="action" value="update_mento">
        <?php
        $mentoStartLocal = '';
        $mentoEndLocal = '';
        if (!empty($regStartValue)) {
            $dt3 = DateTime::createFromFormat('Y-m-d H:i:s', $regStartValue);
            if ($dt3) {
                $mentoStartLocal = $dt3->format('Y-m-d\TH:i');
            }
        }
        if (!empty($regEndValue)) {
            $dt4 = DateTime::createFromFormat('Y-m-d H:i:s', $regEndValue);
            if ($dt4) {
                $mentoEndLocal = $dt4->format('Y-m-d\TH:i');
            }
        }
        ?>
        <div class="flex items-center space-x-2 mb-2">
          <label class="font-medium">등록 시작 시각:</label>
          <input type="datetime-local" name="mento_reg_start" required value="<?php echo htmlspecialchars($mentoStartLocal); ?>">
        </div>
        <div class="flex items-center space-x-2 mb-2">
          <label class="font-medium">등록 종료 시각:</label>
          <input type="datetime-local" name="mento_reg_end" required value="<?php echo htmlspecialchars($mentoEndLocal); ?>">
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">저장하기</button>
      </form>
    </section>

    <!-- [멘토 시청 목록] -->
    <section class="mb-8">
      <h2 class="text-xl font-bold mb-2">멘토 시청 목록</h2>
      <div class="bg-white rounded shadow p-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b bg-gray-50">
            <tr>
              <th class="p-2 text-left">멘토번호</th>
              <th class="p-2 text-left">이름</th>
              <th class="p-2 text-left">경력</th>
              <th class="p-2 text-left">멘티 정원</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($mentoList): ?>
              <?php foreach ($mentoList as $m): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="p-2"><?php echo htmlspecialchars($m['mento_no']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($m['mento_name']); ?></td>
                <td class="p-2 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($m['mento_cv'])); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($m['menti_limit']); ?></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="p-2 text-center text-gray-500">등록된 멘토가 없습니다.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- [멘티 신청 현황] -->
    <section class="mb-8">
      <h2 class="text-xl font-bold mb-2">멘티 신청 현황</h2>
      <div class="bg-white rounded shadow p-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b bg-gray-50">
            <tr>
              <th class="p-2 text-left">신청ID</th>
              <th class="p-2 text-left">멘토번호</th>
              <th class="p-2 text-left">멘토이름</th>
              <th class="p-2 text-left">학번</th>
              <th class="p-2 text-left">성명</th>
              <th class="p-2 text-left">전화번호</th>
              <th class="p-2 text-left">신청시간</th>
              <th class="p-2 text-left">IP</th>
              <th class="p-2 text-left">삭제</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($applyList): ?>
              <?php foreach ($applyList as $app): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="p-2"><?php echo htmlspecialchars($app['apply_id']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($app['mento_no']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($app['mento_name']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($app['student_id']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($app['student_name']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($app['student_phone']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($app['apply_time']); ?></td>
                <td class="p-2"><?php echo htmlspecialchars($app['apply_ip']); ?></td>
                <td class="p-2">
                  <a href="?delete_id=<?php echo $app['apply_id']; ?>" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs" onclick="return confirm('정말 삭제하시겠습니까?')">삭제</a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" class="p-2 text-center text-gray-500">신청 내역이 없습니다.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <!-- 하단 문구 (footer) -->
  <footer class="mt-auto text-center text-sm text-gray-500 py-2">
      © 2025 경기과학고등학교 학생회<br>
      Developed by 전교부회장 장민서
  </footer>
</body>
</html>
