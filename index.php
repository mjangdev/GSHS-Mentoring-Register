<?php
// index.php
session_start();
require_once 'pdo.php';

// === 팝업 메시지(모달) 세션 처리 ===
$popupMessage = $_SESSION['popup_message'] ?? '';
$popupType    = $_SESSION['popup_type'] ?? 'info';
unset($_SESSION['popup_message'], $_SESSION['popup_type']);

// === 현재 시간 (서버 시각) ===
$now = new DateTime();

// === 멘티 신청 시간 설정 (apply_start, apply_end) ===
$sqlApplyStart = "SELECT config_value FROM system_config WHERE config_key='apply_start'";
$stmtApplyStart = $pdo->prepare($sqlApplyStart);
$stmtApplyStart->execute();
$applyStartValue = $stmtApplyStart->fetchColumn();

$sqlApplyEnd = "SELECT config_value FROM system_config WHERE config_key='apply_end'";
$stmtApplyEnd = $pdo->prepare($sqlApplyEnd);
$stmtApplyEnd->execute();
$applyEndValue = $stmtApplyEnd->fetchColumn();

// DateTime 파싱
$applyStart = (!empty($applyStartValue)) ? DateTime::createFromFormat('Y-m-d H:i:s', $applyStartValue) : null;
$applyEnd   = (!empty($applyEndValue))   ? DateTime::createFromFormat('Y-m-d H:i:s', $applyEndValue)   : null;

// 멘티 상태: not_started, open, closed
$menteeStatus = '';
if ($applyStart && $now < $applyStart) {
    $menteeStatus = 'not_started';
} elseif ($applyStart && $applyEnd && $now >= $applyStart && $now < $applyEnd) {
    $menteeStatus = 'open';
} elseif ($applyEnd && $now >= $applyEnd) {
    $menteeStatus = 'closed';
}

// === 멘토 등록 시간 설정 (mento_reg_start, mento_reg_end) ===
$sqlRegStart = "SELECT config_value FROM system_config WHERE config_key='mento_reg_start'";
$stmtRegStart = $pdo->prepare($sqlRegStart);
$stmtRegStart->execute();
$regStartValue = $stmtRegStart->fetchColumn();

$sqlRegEnd = "SELECT config_value FROM system_config WHERE config_key='mento_reg_end'";
$stmtRegEnd = $pdo->prepare($sqlRegEnd);
$stmtRegEnd->execute();
$regEndValue = $stmtRegEnd->fetchColumn();

// DateTime 파싱
$regStart = (!empty($regStartValue)) ? DateTime::createFromFormat('Y-m-d H:i:s', $regStartValue) : null;
$regEnd   = (!empty($regEndValue))   ? DateTime::createFromFormat('Y-m-d H:i:s', $regEndValue)   : null;

// 멘토 등록 상태: not_started, open, closed
$mentoRegStatus = '';
if ($regStart && $now < $regStart) {
    $mentoRegStatus = 'not_started';
} elseif ($regStart && $regEnd && $now >= $regStart && $now < $regEnd) {
    $mentoRegStatus = 'open';
} elseif ($regEnd && $now >= $regEnd) {
    $mentoRegStatus = 'closed';
}

// === 신청정보 조회 (check_info) ===
$checkResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_info') {
    $cid   = trim($_POST['check_student_id'] ?? '');
    $cname = trim($_POST['check_student_name'] ?? '');
    if ($cid !== '' && $cname !== '') {
        $sqlCheck = "
            SELECT a.apply_id, a.mento_no, i.mento_name, a.apply_time
            FROM menti_apply a
            JOIN mento_info i ON a.mento_no = i.mento_no
            WHERE a.student_id = :sid AND a.student_name = :sname
            ORDER BY a.apply_id DESC
        ";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([':sid'=>$cid, ':sname'=>$cname]);
        $checkResults = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
    }
}

// === 멘토 등록 처리 (파일 업로드) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mento_reg') {
    // 등록 기간인지 확인
    if ($mentoRegStatus !== 'open') {
        $_SESSION['popup_message'] = "현재는 멘토 등록 기간이 아닙니다.";
        $_SESSION['popup_type']    = "error";
        header('Location: index.php');
        exit;
    }
    $newName  = trim($_POST['new_mento_name'] ?? '');
    $newCV    = trim($_POST['new_mento_cv']   ?? '');
    $newLimit = trim($_POST['new_menti_limit'] ?? '');

    if ($newName === '' || $newCV === '' || $newLimit === '') {
        $_SESSION['popup_message'] = "멘토 이름, 경력, 멘티 정원은 필수입니다.";
        $_SESSION['popup_type'] = "error";
        header('Location: index.php');
        exit;
    }
    if (!ctype_digit($newLimit)) {
        $_SESSION['popup_message'] = "멘티 정원은 숫자만 입력하세요.";
        $_SESSION['popup_type'] = "error";
        header('Location: index.php');
        exit;
    }
    if (empty($_FILES['new_mento_poster']['name']) || empty($_FILES['new_mento_photo']['name'])) {
        $_SESSION['popup_message'] = "포스터와 본인 사진을 모두 업로드해야 합니다.";
        $_SESSION['popup_type'] = "error";
        header('Location: index.php');
        exit;
    }

    // DB insert
    $sqlInsert = "
        INSERT INTO mento_info (mento_name, mento_cv, menti_limit)
        VALUES (:mname, :mcv, :mlimit)
    ";
    $stmtIns = $pdo->prepare($sqlInsert);
    try {
        $stmtIns->execute([
            ':mname'  => $newName,
            ':mcv'    => $newCV,
            ':mlimit' => (int)$newLimit
        ]);
        $newMentoNo = $pdo->lastInsertId();

        // 파일 업로드 디렉토리
        $uploadDir = __DIR__ . '/mento_poster';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 포스터 업로드
        $posterTmp = $_FILES['new_mento_poster']['tmp_name'];
        $posterMime = mime_content_type($posterTmp);
        if (in_array($posterMime, ['image/png','image/jpeg'])) {
            $posterPath = $uploadDir . '/' . $newMentoNo . '_poster.png';
            move_uploaded_file($posterTmp, $posterPath);
        } else {
            $_SESSION['popup_message'] = "포스터는 PNG/JPEG 파일이어야 합니다.";
            $_SESSION['popup_type'] = "error";
            header('Location: index.php');
            exit;
        }

        // 본인 사진 업로드
        $photoTmp = $_FILES['new_mento_photo']['tmp_name'];
        $photoMime = mime_content_type($photoTmp);
        if (in_array($photoMime, ['image/png','image/jpeg'])) {
            $photoPath = $uploadDir . '/' . $newMentoNo . '_photo.png';
            move_uploaded_file($photoTmp, $photoPath);
        } else {
            $_SESSION['popup_message'] = "본인 사진은 PNG/JPEG 파일이어야 합니다.";
            $_SESSION['popup_type'] = "error";
            header('Location: index.php');
            exit;
        }

        $_SESSION['popup_message'] = "새 멘토(#{$newMentoNo}) 등록 완료!";
        $_SESSION['popup_type']    = "success";
    } catch (PDOException $e) {
        $_SESSION['popup_message'] = "멘토 등록 중 오류: " . $e->getMessage();
        $_SESSION['popup_type']    = "error";
    }
    header('Location: index.php');
    exit;
}

// === 멘티 신청 처리 (apply) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    if ($menteeStatus !== 'open') {
        $_SESSION['popup_message'] = "멘티 신청은 신청 기간 내에만 가능합니다.";
        $_SESSION['popup_type']    = "error";
        header('Location: index.php');
        exit;
    }
    $mento_no     = $_POST['mento_no'] ?? '';
    $student_id   = trim($_POST['student_id'] ?? '');
    $student_name = trim($_POST['student_name'] ?? '');
    $student_phone= trim($_POST['student_phone'] ?? '');
    $apply_time   = date('Y-m-d H:i:s');
    $apply_ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if ($mento_no === '' || $student_id === '' || $student_name === '' || $student_phone === '') {
        $_SESSION['popup_message'] = "필수 입력값이 비어있습니다.";
        $_SESSION['popup_type']    = "error";
        header('Location: index.php');
        exit;
    }

    // 트랜잭션 시작
    $pdo->beginTransaction();
    try {
        // 중복 신청 체크
        $dupSql = "
            SELECT COUNT(*) 
            FROM menti_apply
            WHERE mento_no = :mno
              AND student_id = :sid
              AND student_name = :sname
        ";
        $chkStmt = $pdo->prepare($dupSql);
        $chkStmt->execute([
            ':mno'  => $mento_no,
            ':sid'  => $student_id,
            ':sname'=> $student_name
        ]);
        $dupCount = $chkStmt->fetchColumn();
        if ($dupCount > 0) {
            $pdo->rollBack();
            $_SESSION['popup_message'] = "이미 {$mento_no}조 멘토에게 신청하셨습니다.";
            $_SESSION['popup_type']    = "error";
            header('Location: index.php');
            exit;
        }

        // 현재 지원자 수 확인 (행 잠금)
        $countSql = "SELECT COUNT(*) FROM menti_apply WHERE mento_no = :mno FOR UPDATE";
        $stmtC = $pdo->prepare($countSql);
        $stmtC->execute([':mno' => $mento_no]);
        $currentCount = $stmtC->fetchColumn();

        // 멘티 정원 확인 (행 잠금)
        $limitSql = "SELECT menti_limit FROM mento_info WHERE mento_no = :mno FOR UPDATE";
        $stmtL = $pdo->prepare($limitSql);
        $stmtL->execute([':mno' => $mento_no]);
        $mentiLimit = $stmtL->fetchColumn();

        if ($currentCount >= $mentiLimit) {
            $pdo->rollBack();
            $_SESSION['popup_message'] = "정원이 마감되었습니다.";
            $_SESSION['popup_type']    = "error";
            header('Location: index.php');
            exit;
        }

        // 실제 신청 Insert
        $sqlIns = "
            INSERT INTO menti_apply (mento_no, student_id, student_name, student_phone, apply_time, apply_ip)
            VALUES (:mno, :sid, :sname, :sphone, :atime, :aip)
        ";
        $stmtIns = $pdo->prepare($sqlIns);
        $stmtIns->execute([
            ':mno'   => $mento_no,
            ':sid'   => $student_id,
            ':sname' => $student_name,
            ':sphone'=> $student_phone,
            ':atime' => $apply_time,
            ':aip'   => $apply_ip
        ]);

        $pdo->commit();
        $_SESSION['popup_message'] = "신청이 완료되었습니다!";
        $_SESSION['popup_type']    = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['popup_message'] = "신청 중 오류: " . $e->getMessage();
        $_SESSION['popup_type']    = "error";
    }
    header('Location: index.php');
    exit;
}

// === 멘토 목록 조회 ===
$sqlMento = "SELECT mento_no, mento_name, mento_cv, menti_limit FROM mento_info ORDER BY mento_no ASC";
$mentoList = $pdo->query($sqlMento)->fetchAll(PDO::FETCH_ASSOC);

// === 서버 시간(문자열) for JS ===
$serverTimeStr = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8"/>
  <title>멘토링 신청 시스템</title>
  <!-- 파비콘 -->
  <link rel="icon" href="favicon.ico" type="image/x-icon">

  <!-- Tailwind 2.2.19 -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Noto Sans', sans-serif; }
    .modal-bg { background-color: rgba(0,0,0,0.5); }
  </style>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

  <!-- 상단 메뉴바 -->
  <nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-2 py-2 flex items-center justify-between">
      <!-- 좌측: 로고 + 타이틀 -->
      <div class="flex items-center space-x-2">
        <img src="logo.png" alt="logo" class="h-8">
        <span class="text-xl font-bold">멘토링 신청 시스템</span>
      </div>
      <!-- 우측: 신청정보 조회, 멘토 등록 버튼, IP, 서버시간 -->
      <div class="flex items-center space-x-4 text-gray-600 text-sm">
        <button class="bg-blue-100 hover:bg-blue-200 text-gray-800 py-1 px-2 rounded text-xs" onclick="openCheckModal()">
          신청정보 조회 (멘티)
        </button>
        <!-- 멘토 등록 버튼 상태 -->
        <?php if ($mentoRegStatus === 'open'): ?>
          <button class="bg-green-500 hover:bg-green-600 text-white py-1 px-2 rounded text-xs" onclick="openMentoRegModal()">
            멘토 등록하기
          </button>
        <?php elseif ($mentoRegStatus === 'not_started'): ?>
          <button class="bg-purple-300 text-gray-800 py-1 px-2 rounded text-xs cursor-not-allowed" disabled>
            멘토 등록 시작 전
          </button>
        <?php else: ?>
          <!-- closed -->
          <button class="bg-gray-400 text-white py-1 px-2 rounded text-xs" disabled>
            멘토 등록 종료됨
          </button>
        <?php endif; ?>

        <span>접속 IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></span>
        <span id="serverTime"><?php echo $serverTimeStr; ?></span>
      </div>
    </div>
  </nav>

  <!-- 빨간 배너: 중복 신청 불가 안내 -->
  <div class="bg-red-500 text-white text-center py-2 px-2 text-sm">
    중복 신청은 불가능합니다. 재신청은 학생회장 <strong>정지훈</strong>, 학생부회장 <strong>고현민</strong>, 학생부회장 <strong>장민서</strong>에게 문의하세요.
    <br>*멘토 등록 수정 관련 문의는 학생부회장 <strong>장민서</strong>에게 문의하세요.
  </div>

  <!-- 멘티 신청 배너 -->
  <?php if ($applyStart): ?>
    <?php if ($menteeStatus === 'not_started'): ?>
      <div id="menteeBanner" class="bg-yellow-300 text-center py-2 px-2 text-sm">
        <span id="menteeCountdownText">계산 중...</span>
        (신청 시작: <?php echo htmlspecialchars($applyStart->format('Y-m-d H:i:s')); ?>)
      </div>
    <?php elseif ($menteeStatus === 'open'): ?>
      <div id="menteeBanner" class="bg-green-300 text-center py-2 px-2 text-sm">
        <span id="menteeCountdownText">계산 중...</span>
        (신청 종료: <?php echo htmlspecialchars($applyEnd->format('Y-m-d H:i:s')); ?>)
      </div>
    <?php elseif ($menteeStatus === 'closed'): ?>
      <div class="bg-gray-400 text-center py-2 px-2 text-sm">
        멘티 신청이 종료되었습니다.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- 메인 컨텐츠 -->
  <div class="flex-grow">
    <!-- 팝업 메시지 모달 -->
    <?php if (!empty($popupMessage)): ?>
      <div 
        id="popupModal"
        class="fixed inset-0 z-50 flex items-center justify-center modal-bg"
      >
        <div class="bg-white rounded p-6 max-w-md w-full relative">
          <button 
            class="absolute top-2 right-2 text-gray-500"
            onclick="document.getElementById('popupModal').remove();"
          >
            X
          </button>
          <div class="text-center">
            <?php if ($popupType === 'success'): ?>
              <h2 class="text-green-600 font-bold mb-2">알림</h2>
            <?php elseif ($popupType === 'error'): ?>
              <h2 class="text-red-600 font-bold mb-2">오류</h2>
            <?php else: ?>
              <h2 class="text-blue-600 font-bold mb-2">안내</h2>
            <?php endif; ?>
            <p><?php echo nl2br(htmlspecialchars($popupMessage)); ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- 멘토 카드 목록 -->
    <div class="max-w-7xl mx-auto px-2 py-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <?php foreach($mentoList as $mento):
        $mento_no    = $mento['mento_no'];
        $mento_name  = $mento['mento_name'];
        $mento_cv    = $mento['mento_cv'];
        $menti_limit = $mento['menti_limit'];

        // 현재 신청자 수
        $countSql = "SELECT COUNT(*) FROM menti_apply WHERE mento_no = :mno";
        $stmtC = $pdo->prepare($countSql);
        $stmtC->execute([':mno'=>$mento_no]);
        $applyCount = $stmtC->fetchColumn();

        $isFull = ($applyCount >= $menti_limit);
      ?>
      <div class="bg-white rounded shadow p-4 flex flex-col">
        <!-- 포스터 이미지 -->
        <a href="mento_poster/<?php echo $mento_no; ?>_poster.png"><img 
          src="mento_poster/<?php echo $mento_no; ?>_poster.png"
          alt="포스터"
          class="w-full object-cover rounded mb-2"
          onerror="this.src='https://via.placeholder.com/300x200?text=No+Poster';"
        ></a>
        <div class="text-lg font-bold mb-1">
          <?php echo $mento_no; ?>조 <?php echo htmlspecialchars($mento_name); ?>
        </div>
        <div class="mb-2">
          현재 신청자 수: 
          <span class="<?php echo $isFull ? 'text-red-500 font-bold' : ''; ?>">
            <?php echo $applyCount . '/' . $menti_limit; ?>
          </span>
        </div>
        <div class="mt-auto flex space-x-2">
          <button 
            class="bg-gray-300 hover:bg-gray-400 text-black py-1 px-2 rounded text-sm"
            onclick='openDetailModal(<?php echo json_encode($mento_no); ?>, <?php echo json_encode($mento_name); ?>, <?php echo json_encode($mento_cv); ?>)'
          >
            더보기
          </button>
          <?php
          // 신청 가능 여부
          $canApply = ($menteeStatus === 'open' && !$isFull);
          if ($canApply): ?>
            <button 
              class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-2 rounded text-sm"
              onclick="openApplyModal('<?php echo $mento_no; ?>','<?php echo htmlspecialchars($mento_name); ?>')"
            >
              멘티 지원하기
            </button>
          <?php else: ?>
            <?php if ($menteeStatus === 'not_started'): ?>
              <button class="bg-red-300 cursor-not-allowed text-white py-1 px-2 rounded text-sm" disabled>
                신청 시작 전
              </button>
            <?php else: ?>
              <!-- 종료/정원초과 등 -->
              <button class="bg-red-300 cursor-not-allowed text-white py-1 px-2 rounded text-sm" disabled>
                신청 불가
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 하단 문구 (footer) -->
  <footer class="mt-auto text-center text-sm py-2">
      <strong>© 2025 경기과학고등학교 학생회 <br>
      Developed by 전교부회장 장민서</strong>
  </footer>

<!-- 모달: 더보기 -->
<div id="detailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-bg">
  <div class="bg-white rounded p-4 w-11/12 max-w-xl relative" style="max-height: 80vh; overflow-y: auto;">
    <button class="absolute top-2 right-2 text-gray-500" onclick="closeDetailModal()">X</button>
    <div id="detailModalContent"></div>
  </div>
</div>

  <!-- 모달: 멘티 신청 -->
  <div id="applyModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-bg">
    <div class="bg-white rounded p-4 w-11/12 max-w-md relative">
      <button class="absolute top-2 right-2 text-gray-500" onclick="closeApplyModal()">X</button>
      <h2 class="text-xl font-bold mb-2" id="applyModalTitle"></h2>
      <form method="POST" action="">
        <input type="hidden" name="action" value="apply">
        <input type="hidden" name="mento_no" id="applyMentoNo" value="">
        <div class="mb-2">
          <label class="block text-sm font-medium">학번</label>
          <input type="text" name="student_id" required class="border rounded w-full p-1">
        </div>
        <div class="mb-2">
          <label class="block text-sm font-medium">성명</label>
          <input type="text" name="student_name" required class="border rounded w-full p-1">
        </div>
        <div class="mb-2">
          <label class="block text-sm font-medium">전화번호</label>
          <input type="text" name="student_phone" required class="border rounded w-full p-1">
        </div>
        <p class="text-xs text-gray-500 mt-2">
          중복 신청은 불가능합니다. 재신청은 학생회장 정지훈, 학생부회장 고현민, 학생부회장 장민서에게 문의하세요.
        </p>
        <div class="mt-4 text-right">
          <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded">
            신청하기
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 모달: 멘토 등록 (파일 업로드) -->
  <div id="mentoRegModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-bg">
    <div class="bg-white rounded p-4 w-11/12 max-w-md relative">
      <button class="absolute top-2 right-2 text-gray-500" onclick="closeMentoRegModal()">X</button>
      <h2 class="text-xl font-bold mb-2">멘토 등록</h2>
      <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="mento_reg">
        <div class="mb-2">
          <label class="block text-sm font-medium">멘토팀 팀원 이름(학번)</label>
          <input type="text" name="new_mento_name" required class="border rounded w-full p-1">
        </div>
        <div class="mb-2">
          <label class="block text-sm font-medium">멘토팀 팀원 경력</label>
          <textarea name="new_mento_cv" required class="border rounded w-full p-1" rows="3"></textarea>
        </div>
        <div class="mb-2">
          <label class="block text-sm font-medium">멘티 정원</label>
          <input type="number" name="new_menti_limit" min="1" required class="border rounded w-full p-1">
        </div>
        <div class="mb-2">
          <label class="block text-sm font-medium">멘토팀 포스터 이미지</label>
          <input type="file" name="new_mento_poster" accept="image/*" required>
        </div>
        <div class="mb-2">
          <label class="block text-sm font-medium">멘토팀 팀원 사진 (한장으로 묶어서)</label>
          <input type="file" name="new_mento_photo" accept="image/*" required>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          멘토 등록 기간에만 신규 멘토 추가가 가능합니다.
        </p>
        <div class="mt-4 text-right">
          <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded">
            등록하기
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 모달: 신청정보 조회 -->
  <div id="checkModal" class="hidden fixed inset-0 z-50 flex items-center justify-center modal-bg">
    <div class="bg-white rounded p-4 w-11/12 max-w-md relative">
      <button class="absolute top-2 right-2 text-gray-500" onclick="closeCheckModal()">X</button>
      <h2 class="text-xl font-bold mb-2">신청정보 조회 (멘티)</h2>
      <form method="POST" action="" class="mb-4">
        <input type="hidden" name="action" value="check_info">
        <div class="mb-2">
          <label class="block text-sm font-medium">학번</label>
          <input type="text" name="check_student_id" required class="border rounded w-full p-1">
        </div>
        <div class="mb-2">
          <label class="block text-sm font-medium">이름</label>
          <input type="text" name="check_student_name" required class="border rounded w-full p-1">
        </div>
        <div class="mt-4 text-right">
          <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded">
            조회
          </button>
        </div>
      </form>
      <?php if (!empty($checkResults)): ?>
        <div class="bg-gray-100 p-2 rounded text-sm">
          <?php foreach($checkResults as $r): ?>
            <div class="mb-1">
              - 신청ID: <?php echo htmlspecialchars($r['apply_id']); ?>
              | <?php echo htmlspecialchars($r['mento_no']); ?>조 
              <?php echo htmlspecialchars($r['mento_name']); ?>
              (<?php echo htmlspecialchars($r['apply_time']); ?>)
            </div>
          <?php endforeach; ?>
        </div>
      <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'check_info'): ?>
        <div class="text-sm text-red-500">해당 학번/이름으로 신청 내역이 없습니다.</div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // 서버 시간 실시간 업데이트
    let serverTime = new Date("<?php echo $serverTimeStr; ?>");
    function updateTime() {
      serverTime.setSeconds(serverTime.getSeconds() + 1);
      const yyyy = serverTime.getFullYear();
      const mm   = String(serverTime.getMonth() + 1).padStart(2, '0');
      const dd   = String(serverTime.getDate()).padStart(2, '0');
      const hh   = String(serverTime.getHours()).padStart(2, '0');
      const mi   = String(serverTime.getMinutes()).padStart(2, '0');
      const ss   = String(serverTime.getSeconds()).padStart(2, '0');
      document.getElementById('serverTime').textContent = 
        `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
    }
    setInterval(updateTime, 1000);

    // 멘티 신청 카운트다운
    <?php if ($applyStart && $menteeStatus === 'not_started'): ?>
    (function(){
      const cdText = document.getElementById('menteeCountdownText');
      if (!cdText) return;
      const target = new Date("<?php echo $applyStart->format('Y-m-d\TH:i:s'); ?>");
      function updateCountdown() {
        const now = new Date();
        const diff = target - now;
        if (diff <= 0) {
          cdText.textContent = "신청이 시작되었습니다.";
          return;
        }
        const sec  = Math.floor(diff / 1000);
        const days = Math.floor(sec / 86400);
        const hrs  = Math.floor((sec % 86400) / 3600);
        const mins = Math.floor((sec % 3600) / 60);
        const secs = sec % 60;
        cdText.textContent = `멘티 신청 시작까지 ${days}일 ${hrs}시간 ${mins}분 ${secs}초 남았습니다.`;
      }
      updateCountdown();
      setInterval(updateCountdown, 1000);
    })();
    <?php elseif ($applyStart && $menteeStatus === 'open'): ?>
    (function(){
      const cdText = document.getElementById('menteeCountdownText');
      if (!cdText) return;
      const target = new Date("<?php echo $applyEnd->format('Y-m-d\TH:i:s'); ?>");
      function updateCountdown() {
        const now = new Date();
        const diff = target - now;
        if (diff <= 0) {
          cdText.textContent = "멘티 신청이 종료되었습니다.";
          return;
        }
        const sec  = Math.floor(diff / 1000);
        const days = Math.floor(sec / 86400);
        const hrs  = Math.floor((sec % 86400) / 3600);
        const mins = Math.floor((sec % 3600) / 60);
        const secs = sec % 60;
        cdText.textContent = `멘티 신청 종료까지 ${days}일 ${hrs}시간 ${mins}분 ${secs}초 남았습니다.`;
      }
      updateCountdown();
      setInterval(updateCountdown, 1000);
    })();
    <?php endif; ?>

    // 모달: 더보기
    function openDetailModal(no, name, cv) {
      const modal = document.getElementById('detailModal');
      const content = document.getElementById('detailModalContent');
      const photoUrl = `mento_poster/${no}_photo.png`;
      content.innerHTML = `
        <h2 class="text-xl font-bold mb-2">${no}조 ${name}</h2>
        <img src="${photoUrl}" alt="멘토 사진" class="object-cover rounded mb-2"
             onerror="this.src='https://via.placeholder.com/150?text=No+Photo';">
        <div>
          <p class="font-semibold mb-1">멘토 경력</p>
          <p class="text-sm whitespace-pre-line">${cv}</p>
        </div>
      `;
      modal.classList.remove('hidden');
    }
    function closeDetailModal() {
      document.getElementById('detailModal').classList.add('hidden');
    }

    // 모달: 멘티 신청
    function openApplyModal(no, name) {
      document.getElementById('applyMentoNo').value = no;
      document.getElementById('applyModalTitle').innerText = no + "조 " + name + " 신청하기";
      document.getElementById('applyModal').classList.remove('hidden');
    }
    function closeApplyModal() {
      document.getElementById('applyModal').classList.add('hidden');
    }

    // 모달: 멘토 등록
    function openMentoRegModal() {
      document.getElementById('mentoRegModal').classList.remove('hidden');
    }
    function closeMentoRegModal() {
      document.getElementById('mentoRegModal').classList.add('hidden');
    }

    // 모달: 신청정보 조회
    function openCheckModal() {
      document.getElementById('checkModal').classList.remove('hidden');
    }
    function closeCheckModal() {
      document.getElementById('checkModal').classList.add('hidden');
    }
    <?php if (!empty($_POST['action']) && $_POST['action'] === 'check_info'): ?>
      // POST로 check_info가 왔으면, 조회 결과 모달을 자동 표시
      document.getElementById('checkModal').classList.remove('hidden');
    <?php endif; ?>
  </script>
</body>
</html>
