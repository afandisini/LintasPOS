<?php
// app/Views/partials/shared/toast.php
?>
<div class="toast-wrap" id="toastWrap"></div>
<script>
window.__APP_TOASTS = <?= raw(toast_payload_json()) ?>;
</script>
