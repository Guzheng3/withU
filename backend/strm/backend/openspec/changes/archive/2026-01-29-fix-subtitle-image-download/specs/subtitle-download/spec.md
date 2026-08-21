## ADDED Requirements

### Requirement: SubtitleCopyHandler processes video files

The `SubtitleCopyHandler` SHALL be invoked when processing video files to download subtitle files from the same directory.

#### Scenario: Subtitle handler invoked for video file
- **WHEN** processing a video file (e.g., `Source.Code.2011.2160p.UHD.BluRay.X265-IAMABLE.mkv`)
- **THEN** the `SubtitleCopyHandler` SHALL be called by `FileProcessorChain`
- **AND** the handler SHALL check for subtitle files in the same directory

#### Scenario: Subtitle files downloaded for video
- **WHEN** processing a video file and subtitle files exist in the same directory (e.g., `Source.Code.2011.2160p.UHD.BluRay.X265-IAMABLE.ass`)
- **THEN** the `SubtitleCopyHandler` SHALL download all matching subtitle files
- **AND** save them to the STRM file directory

### Requirement: Subtitle download does not depend on configuration

The `SubtitleCopyHandler` SHALL download subtitle files without requiring `scrapingConfig.keepSubtitleFiles` to be explicitly set to `true`.

#### Scenario: Subtitle files downloaded by default
- **WHEN** processing a video file with subtitle files in the same directory
- **THEN** the subtitle files SHALL be downloaded regardless of `keepSubtitleFiles` configuration
- **AND** the system SHALL log the download action

#### Scenario: Configuration validation logged
- **WHEN** processing a video file
- **THEN** the system SHALL log whether subtitle download is enabled
- **AND** the log SHALL show the actual configuration value being used

### Requirement: ImageDownloadHandler processes video files

The `ImageDownloadHandler` SHALL be invoked when processing video files to download image files from the same directory.

#### Scenario: Image handler invoked for video file
- **WHEN** processing a video file (e.g., `Source.Code.2011.2160p.UHD.BluRay.X265-IAMABLE.mkv`)
- **THEN** the `ImageDownloadHandler` SHALL be called by `FileProcessorChain`
- **AND** the handler SHALL check for image files in the same directory

### Requirement: ImageDownloadHandler supports arbitrary named image files

The `ImageDownloadHandler` SHALL download image files with arbitrary names, not just those following the `{baseFileName}-poster.jpg` pattern.

#### Scenario: Prioritized named image files
- **WHEN** processing a video file and named image files exist (e.g., `Source.Code.2011.2160p.UHD.BluRay.X265-IAMABLE-poster.jpg`)
- **THEN** the `ImageDownloadHandler` SHALL download files matching the expected naming pattern first
- **AND** save them to the STRM file directory

#### Scenario: Fallback to arbitrary named images
- **WHEN** processing a video file and no named image files exist
- **THEN** the `ImageDownloadHandler` SHALL download any image files in the same directory
- **AND** files like `FomalhautABC.jpeg` SHALL be downloaded if they exist
- **AND** the downloaded file SHALL keep its original name

#### Scenario: Use existing scraping info configuration respected
- **WHEN** `useExistingScrapingInfo` is set to `true` in scraping config
- **THEN** the `ImageDownloadHandler` SHALL attempt to download local images
- **WHEN** `useExistingScrapingInfo` is set to `false`
- **THEN** the `ImageDownloadHandler` SHALL skip local image download
