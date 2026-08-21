## ADDED Requirements

### Requirement: OrphanCleanupHandler uses original file list

The `OrphanCleanupHandler` SHALL use the original complete file list (containing all file types) retrieved from OpenList to determine whether a file is orphaned, rather than the filtered video-only file list.

#### Scenario: NFO file not marked as orphaned when video exists
- **WHEN** a video file has a corresponding NFO file and the video exists in OpenList
- **THEN** the NFO file SHALL NOT be considered orphaned
- **AND** the NFO file SHALL NOT be deleted

#### Scenario: Image files not marked as orphaned when video exists
- **WHEN** a video file has corresponding poster and backdrop image files and the video exists in OpenList
- **THEN** the image files SHALL NOT be considered orphaned
- **AND** the image files SHALL NOT be deleted

#### Scenario: Subtitle files not marked as orphaned when video exists
- **WHEN** a video file has corresponding subtitle files and the video exists in OpenList
- **THEN** the subtitle files SHALL NOT be considered orphaned
- **AND** the subtitle files SHALL NOT be deleted

### Requirement: TaskExecutionService preserves original file list

The `TaskExecutionService.executeTaskWithHandlerChain` SHALL preserve the original complete file list in a separate context attribute named `originalFiles`, distinct from the filtered `videoFiles` attribute.

#### Scenario: Original file list stored in context
- **WHEN** a task is executed
- **THEN** the original file list SHALL be stored in context with attribute key `originalFiles`
- **AND** the filtered video file list SHALL be stored with attribute key `videoFiles`

### Requirement: FileFilterHandler does not overwrite discoveredFiles

The `FileFilterHandler` SHALL NOT overwrite the `discoveredFiles` context attribute with only video files.

#### Scenario: Filtered video files stored separately
- **WHEN** file filtering is performed
- **THEN** video files SHALL be stored in `videoFiles` attribute
- **AND** `discoveredFiles` SHALL retain the original file list

### Requirement: Orphaned STRM files are correctly identified

The `OrphanCleanupHandler` SHALL correctly identify STRM files as orphaned only when their corresponding source video file does not exist in OpenList.

#### Scenario: STRM file deleted when source video is removed
- **WHEN** a STRM file exists but its source video file is no longer in OpenList
- **THEN** the STRM file SHALL be considered orphaned
- **AND** the STRM file SHALL be deleted
- **AND** associated NFO, image, and subtitle files SHALL be deleted

#### Scenario: STRM file retained when source video exists
- **WHEN** a STRM file exists and its source video file is present in OpenList
- **THEN** the STRM file SHALL NOT be considered orphaned
- **AND** the STRM file SHALL be retained

### Requirement: File matching handles Chinese paths correctly

The file matching logic in `OrphanCleanupHandler` SHALL correctly handle file names containing Chinese characters and special characters.

#### Scenario: Chinese file name matching
- **WHEN** checking if a file exists in OpenList with a Chinese file name
- **THEN** the matching logic SHALL use the original file name without removing Chinese characters
- **AND** the matching SHALL be case-insensitive for English letters

#### Scenario: Special character handling in file names
- **WHEN** checking if a file exists in OpenList with special characters (spaces, parentheses, etc.)
- **THEN** the matching logic SHALL handle special characters correctly
- **AND** files with names differing only in special character representation SHALL be matched correctly
