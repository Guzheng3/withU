cmake_minimum_required(VERSION 3.21)

project(withu_player VERSION 0.2.0 LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_CXX_EXTENSIONS OFF)

find_package(Qt6 6.5 REQUIRED COMPONENTS Widgets Multimedia MultimediaWidgets Network)

set(WEBVIEW2_SDK_ROOT "${CMAKE_CURRENT_SOURCE_DIR}/../../../tools/webview2-sdk/package" CACHE PATH "WebView2 SDK root")
set(WEBVIEW2_INCLUDE_DIR "${WEBVIEW2_SDK_ROOT}/build/native/include")
if (EXISTS "${WEBVIEW2_INCLUDE_DIR}/WebView2.h")
    set(WITHU_WEBVIEW2_AVAILABLE ON)
else()
    set(WITHU_WEBVIEW2_AVAILABLE OFF)
endif()

qt_standard_project_setup()

qt_add_executable(withu_player WIN32
    src/main.cpp
    src/MainWindow.cpp
    src/MainWindow.h
    src/WebView2Host.cpp
    src/WebView2Host.h
)

target_link_libraries(withu_player PRIVATE
    Qt6::Widgets
    Qt6::Multimedia
    Qt6::MultimediaWidgets
    Qt6::Network
)

target_include_directories(withu_player PRIVATE
    ${CMAKE_CURRENT_SOURCE_DIR}/third_party/libmpv/include
)

if (WITHU_WEBVIEW2_AVAILABLE)
    target_include_directories(withu_player PRIVATE "${WEBVIEW2_INCLUDE_DIR}")
    target_compile_definitions(withu_player PRIVATE WITHU_WEBVIEW2_AVAILABLE=1)
    target_link_libraries(withu_player PRIVATE ole32 user32)
else()
    target_compile_definitions(withu_player PRIVATE WITHU_WEBVIEW2_AVAILABLE=0)
endif()

set_target_properties(withu_player PROPERTIES
    WIN32_EXECUTABLE TRUE
    OUTPUT_NAME "withU Desktop"
)

install(TARGETS withu_player
    RUNTIME DESTINATION .
    BUNDLE DESTINATION .
)
