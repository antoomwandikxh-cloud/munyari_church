<?php

function cleanup_expired_church_highlight_videos(mysqli $conn): int {
    try {
        $expiry_column = $conn->query("SHOW COLUMNS FROM church_highlights LIKE 'expires_at'");
        if (!$expiry_column) {
            error_log('Unable to inspect church highlight expiry column: ' . $conn->error);
            return 0;
        }
        if ($expiry_column->num_rows === 0 && !$conn->query("ALTER TABLE church_highlights ADD COLUMN expires_at DATETIME NULL AFTER caption")) {
            error_log('Unable to add church highlight expiry column: ' . $conn->error);
            return 0;
        }

        $conn->query("UPDATE church_highlights
            SET expires_at = DATE_ADD(created_at, INTERVAL 1 YEAR)
            WHERE expires_at IS NULL
              AND LOWER(SUBSTRING_INDEX(image_file, '.', -1)) IN ('mp4', 'webm', 'ogg')");

        $expired_videos = $conn->query("SELECT id, image_file FROM church_highlights
            WHERE expires_at IS NOT NULL
              AND expires_at <= NOW()
              AND LOWER(SUBSTRING_INDEX(image_file, '.', -1)) IN ('mp4', 'webm', 'ogg')");
        if (!$expired_videos) {
            error_log('Unable to find expired church highlight videos: ' . $conn->error);
            return 0;
        }

        $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
        $delete_row = $conn->prepare('DELETE FROM church_highlights WHERE id = ? AND expires_at <= NOW()');
        if (!$delete_row) {
            error_log('Unable to prepare expired church highlight deletion: ' . $conn->error);
            return 0;
        }

        $deleted_count = 0;
        while ($video = $expired_videos->fetch_assoc()) {
            $filename = basename((string)$video['image_file']);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($filename === '' || !str_starts_with($filename, 'highlight_') || !in_array($extension, ['mp4', 'webm', 'ogg'], true)) {
                continue;
            }

            $file_path = $upload_dir . DIRECTORY_SEPARATOR . $filename;
            if (is_file($file_path) && !@unlink($file_path)) {
                error_log('Unable to remove expired church highlight file: ' . $file_path);
                continue;
            }

            $video_id = (int)$video['id'];
            $delete_row->bind_param('i', $video_id);
            if ($delete_row->execute()) {
                $deleted_count += $delete_row->affected_rows;
            }
        }

        $delete_row->close();
        $expired_videos->free();
        return $deleted_count;
    } catch (Throwable $error) {
        error_log('Church highlight retention cleanup failed: ' . $error->getMessage());
        return 0;
    }
}
