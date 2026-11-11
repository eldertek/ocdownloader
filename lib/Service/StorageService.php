<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorage;
use Psr\Log\LoggerInterface;
use RuntimeException;

class StorageService {
	public function __construct(
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	public function normalizeRelativePath(string $path, string $fallback = 'Downloads'): string {
		$normalized = trim(str_replace('\\', '/', $path), '/');
		$segments = [];
		foreach (explode('/', $normalized) as $segment) {
			$segment = trim($segment);
			if ($segment === '' || $segment === '.') {
				continue;
			}
			if ($segment === '..') {
				throw new RuntimeException('Invalid folder path');
			}
			$segments[] = $segment;
		}
		if ($segments === []) {
			return $fallback;
		}
		return implode('/', $segments);
	}

	public function ensureUserFolder(string $uid, string $relativePath): Folder {
		$cleanPath = $this->normalizeRelativePath($relativePath);
		$userFolder = $this->rootFolder->getUserFolder($uid);

		$current = $userFolder;
		foreach (explode('/', $cleanPath) as $segment) {
			if ($current->nodeExists($segment)) {
				$node = $current->get($segment);
				if (!$node instanceof Folder) {
					throw new RuntimeException('Target path exists and is not a folder: ' . $cleanPath);
				}
				$current = $node;
			} else {
				$current = $current->newFolder($segment);
			}
		}

		return $current;
	}

	public function getLocalFolderPath(string $uid, string $relativePath): string {
		$folder = $this->ensureUserFolder($uid, $relativePath);
		/** @var IStorage $storage */
		$storage = $folder->getStorage();
		$internalPath = $folder->getInternalPath();
		$localPath = $storage->getLocalFile($internalPath);
		if (!is_string($localPath) || $localPath === '') {
			throw new RuntimeException('Selected folder is not backed by local filesystem storage');
		}
		if (!is_dir($localPath) && !@mkdir($localPath, 0775, true) && !is_dir($localPath)) {
			throw new RuntimeException('Unable to prepare local download directory');
		}
		return rtrim($localPath, '/\\');
	}

	public function nodeExists(string $uid, string $relativePath): bool {
		$cleanPath = $this->normalizeRelativePath($relativePath, '');
		if ($cleanPath === '') {
			return true;
		}
		$userFolder = $this->rootFolder->getUserFolder($uid);
		return $userFolder->nodeExists($cleanPath);
	}

	public function deleteNodeIfExists(string $uid, string $relativePath): void {
		$cleanPath = $this->normalizeRelativePath($relativePath, '');
		if ($cleanPath === '') {
			return;
		}
		try {
			$userFolder = $this->rootFolder->getUserFolder($uid);
			if ($userFolder->nodeExists($cleanPath)) {
				$userFolder->get($cleanPath)->delete();
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Unable to delete file ' . $cleanPath . ': ' . $e->getMessage(), ['app' => 'ocdownloader']);
		}
	}

	/**
	 * @return array<int, array{name:string, size:int, mtime:int}>
	 */
	public function listTorrentFiles(string $uid, string $torrentsFolder): array {
		$folder = $this->ensureUserFolder($uid, $torrentsFolder);
		$this->scanFolder($folder);

		$files = [];
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof File && str_ends_with(strtolower($node->getName()), '.torrent')) {
				$files[] = [
					'name' => $node->getName(),
					'size' => (int)$node->getSize(),
					'mtime' => (int)$node->getMTime(),
				];
			}
		}

		usort($files, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
		return $files;
	}

	public function readTorrentContent(string $uid, string $torrentsFolder, string $fileName): string {
		$safeName = Tools::sanitiseFileName($fileName);
		if (!str_ends_with(strtolower($safeName), '.torrent')) {
			throw new RuntimeException('Invalid torrent file');
		}
		$folder = $this->ensureUserFolder($uid, $torrentsFolder);
		if (!$folder->nodeExists($safeName)) {
			throw new NotFoundException('Torrent file not found');
		}
		$node = $folder->get($safeName);
		if (!$node instanceof File) {
			throw new RuntimeException('Invalid torrent file');
		}
		return $node->getContent();
	}

	public function saveUploadedTorrent(string $uid, string $torrentsFolder, string $originalName, string $tmpPath): string {
		$safeName = Tools::sanitiseFileName($originalName);
		if (!str_ends_with(strtolower($safeName), '.torrent')) {
			throw new RuntimeException('Only .torrent files are allowed');
		}
		$content = @file_get_contents($tmpPath);
		if ($content === false || $content === '') {
			throw new RuntimeException('Uploaded torrent file is empty');
		}

		$folder = $this->ensureUserFolder($uid, $torrentsFolder);
		if ($folder->nodeExists($safeName)) {
			$existing = $folder->get($safeName);
			if ($existing instanceof File) {
				$existing->putContent($content);
				return $safeName;
			}
			$safeName = time() . '_' . $safeName;
		}
		$folder->newFile($safeName, $content);
		return $safeName;
	}

	public function scanDownloadedTarget(string $uid, string $downloadsFolder, ?string $targetName = null): void {
		try {
			$folder = $this->ensureUserFolder($uid, $downloadsFolder);
			$storage = $folder->getStorage();
			$scanner = $storage->getScanner();
			$folderInternal = trim($folder->getInternalPath(), '/');

			if ($targetName !== null && $targetName !== '') {
				$safeTarget = trim($targetName, '/\\');
				if ($safeTarget !== '' && !str_contains($safeTarget, '..')) {
					$targetInternal = $folderInternal !== '' ? ($folderInternal . '/' . $safeTarget) : $safeTarget;
					if ($storage->file_exists($targetInternal)) {
						$scanner->scan($targetInternal);
						return;
					}
				}
			}

			$scanner->scan($folderInternal);
		} catch (\Throwable $e) {
			$this->logger->warning('Storage scan failed: ' . $e->getMessage(), ['app' => 'ocdownloader']);
		}
	}

	public function scanFolder(Folder $folder): void {
		try {
			$folder->getStorage()->getScanner()->scan(trim($folder->getInternalPath(), '/'));
		} catch (\Throwable $e) {
			$this->logger->debug('Folder scan skipped: ' . $e->getMessage(), ['app' => 'ocdownloader']);
		}
	}
}
