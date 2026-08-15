package com.hienao.openlist2strm.title;

import java.util.List;

/** 元数据数据源统一接口。 */
public interface MetadataProvider {

  /** 数据源名称。 */
  String name();

  /** 是否已启用（由配置门控）。 */
  boolean isEnabled();

  /**
   * 按标题搜索元数据。
   *
   * @param title 搜索标题
   * @param year 可选年份
   * @param mediaType movie / tv
   */
  List<MetadataCandidate> search(String title, String year, String mediaType);
}
