package com.hienao.openlist2strm.controller;

import com.hienao.openlist2strm.dto.ApiResponse;
import com.hienao.openlist2strm.dto.media.MediaLibraryDtos;
import com.hienao.openlist2strm.service.MediaLibraryService;
import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import java.util.List;
import lombok.RequiredArgsConstructor;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

/** 媒体库接口。 */
@Tag(name = "媒体库")
@RestController
@RequestMapping("/api/media-library")
@RequiredArgsConstructor
public class MediaLibraryController {

  private final MediaLibraryService mediaLibraryService;

  @Operation(summary = "分页查询媒体库")
  @GetMapping
  public ResponseEntity<ApiResponse<MediaLibraryDtos.PageResult>> query(
      @RequestParam(required = false) Long taskId,
      @RequestParam(required = false) String mediaType,
      @RequestParam(required = false) String keyword,
      @RequestParam(defaultValue = "1") int page,
      @RequestParam(defaultValue = "24") int pageSize) {
    MediaLibraryDtos.PageResult result =
        mediaLibraryService.query(taskId, mediaType, keyword, page, pageSize);
    return ResponseEntity.ok(ApiResponse.success(result));
  }

  @Operation(summary = "获取媒体详情")
  @GetMapping("/{id}")
  public ResponseEntity<ApiResponse<MediaLibraryDtos.Detail>> getDetail(
      @PathVariable Long id) {
    return ResponseEntity.ok(ApiResponse.success(mediaLibraryService.getDetail(id)));
  }

  @Operation(summary = "解析媒体播放地址", description = "同一集多来源时默认按分辨率优先级（4K优先）选择，可用 sourceId 指定来源")
  @GetMapping("/{id}/play")
  public ResponseEntity<ApiResponse<MediaLibraryDtos.PlaybackResult>> resolvePlay(
      @PathVariable Long id, @RequestParam(required = false) Long sourceId) {
    return ResponseEntity.ok(
        ApiResponse.success(mediaLibraryService.resolvePlayback(id, sourceId)));
  }

  @Operation(summary = "媒体库任务筛选项")
  @GetMapping("/tasks")
  public ResponseEntity<ApiResponse<List<MediaLibraryDtos.TaskOption>>> listTasks() {
    return ResponseEntity.ok(ApiResponse.success(mediaLibraryService.listTaskOptions()));
  }
}
