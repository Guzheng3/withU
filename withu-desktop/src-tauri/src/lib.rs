// 导航守卫：只允许绑定域名（含其子路径）与 Tauri 内部页，其余导航一律拦截
fn is_allowed(url: &tauri::Url) -> bool {
    let s = url.as_str();
    s.starts_with("https://gzr.xjy.xn--6qq986b3xl")
        || s.starts_with("tauri://localhost")
        || s.starts_with("http://tauri.localhost")
        || s.starts_with("about:")
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(
            tauri::plugin::Builder::<tauri::Wry>::new("nav-guard")
                .on_navigation(|_webview, url| is_allowed(url))
                .build(),
        )
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
