package com.hienao.openlist2strm.mapper;

import com.hienao.openlist2strm.entity.MediaLibraryItem;
import java.util.List;
import org.apache.ibatis.annotations.Mapper;
import org.apache.ibatis.annotations.Param;

@Mapper
public interface MediaLibraryItemMapper {
  MediaLibraryItem selectById(@Param("id") Long id);

  List<MediaLibraryItem> selectPage(
      @Param("taskId") Long taskId,
      @Param("mediaType") String mediaType,
      @Param("keyword") String keyword,
      @Param("offset") int offset,
      @Param("limit") int limit);

  List<MediaLibraryItem> selectAll(
      @Param("taskId") Long taskId,
      @Param("mediaType") String mediaType,
      @Param("keyword") String keyword);

  long count(
      @Param("taskId") Long taskId,
      @Param("mediaType") String mediaType,
      @Param("keyword") String keyword);

  int upsert(MediaLibraryItem item);

  /** 软删除某 OpenList 配置关联的所有媒体库条目（账号删除时立即从媒体库隐藏）。 */
  int softDeleteByConfigId(@Param("openlistConfigId") Long openlistConfigId);

  /** 恢复某 OpenList 配置关联的所有媒体库条目（取消删除计划重新添加时）。 */
  int restoreByConfigId(@Param("openlistConfigId") Long openlistConfigId);

  /** 查询某 OpenList 配置关联的所有条目（包含已软删除，用于到期物理清理）。 */
  List<MediaLibraryItem> selectByConfigId(@Param("openlistConfigId") Long openlistConfigId);

  /** 物理删除某 OpenList 配置关联的所有媒体库条目（到期清理）。 */
  int deleteByConfigId(@Param("openlistConfigId") Long openlistConfigId);
}
