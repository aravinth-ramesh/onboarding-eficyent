// Must match backend config/onboarding_uploads.php (max_file_size_kb = 5120).
export const MAX_FILE_SIZE_MB = 5;
export const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024;

export function formatFileSize(bytes) {
  if (bytes === null || bytes === undefined || isNaN(bytes)) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Split a candidate file list into accepted files and oversized rejects.
 * Mirrors the server's max size so users get instant feedback instead of a
 * failed upload.
 */
export function partitionBySize(fileList) {
  const accepted = [];
  const rejected = [];
  Array.from(fileList).forEach((file) => {
    (file.size > MAX_FILE_SIZE_BYTES ? rejected : accepted).push(file);
  });
  return { accepted, rejected };
}

export function oversizeMessage(rejected) {
  const names = rejected.map((f) => `${f.name} (${formatFileSize(f.size)})`).join(', ');
  return `Too large — the limit is ${MAX_FILE_SIZE_MB}MB per file: ${names}`;
}
