package com.hienao.openlist2strm.dto.tmdb;

import com.fasterxml.jackson.annotation.JsonProperty;
import java.util.List;
import lombok.Data;

/**
 * TMDB 演员/制作人员信息DTO
 *
 * @author hienao
 * @since 2024-01-01
 */
@Data
public class TmdbCredits {

  /** TMDB ID */
  private Integer id;

  /** 演员列表 */
  private List<Cast> cast;

  /** 剧组人员列表 */
  private List<Crew> crew;

  /** 演员 */
  @Data
  public static class Cast {

    /** 演员ID */
    private Integer id;

    /** 姓名 */
    private String name;

    /** 原始姓名 */
    @JsonProperty("original_name")
    private String originalName;

    /** 角色名 */
    private String character;

    /** 头像路径 */
    @JsonProperty("profile_path")
    private String profilePath;

    /** 排序 */
    private Integer order;

    /** 性别 */
    private Integer gender;

    /** 成人内容标识 */
    private Boolean adult;

    /** 知名度 */
    private Double popularity;

    /** 演员ID（TMDB credit id） */
    @JsonProperty("credit_id")
    private String creditId;
  }

  /** 剧组人员 */
  @Data
  public static class Crew {

    /** 人员ID */
    private Integer id;

    /** 姓名 */
    private String name;

    /** 原始姓名 */
    @JsonProperty("original_name")
    private String originalName;

    /** 职务 */
    private String job;

    /** 部门 */
    private String department;

    /** 头像路径 */
    @JsonProperty("profile_path")
    private String profilePath;

    /** 性别 */
    private Integer gender;
  }
}
