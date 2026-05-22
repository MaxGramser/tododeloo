import Foundation

/// Thin async client over the Tododeloo Laravel API. Token is read from the
/// Keychain on every request so the app and the Siri intent share one source
/// of truth.
final class APIClient {
    static let shared = APIClient()

    enum Method: String {
        case get = "GET"
        case post = "POST"
        case patch = "PATCH"
        case delete = "DELETE"
    }

    private let urlSession: URLSession
    private let decoder: JSONDecoder

    init() {
        #if DEBUG
        urlSession = URLSession(configuration: .default, delegate: DevTrustDelegate(), delegateQueue: nil)
        #else
        urlSession = URLSession(configuration: .default)
        #endif

        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        decoder.dateDecodingStrategy = .iso8601
        self.decoder = decoder
    }

    var isAuthenticated: Bool { Keychain.token != nil }

    // MARK: - Auth

    @discardableResult
    func login(email: String, password: String, deviceName: String) async throws -> LoginResponse {
        let response: LoginResponse = try await send(.post, "login", body: [
            "email": email,
            "password": password,
            "device_name": deviceName,
        ], authenticated: false)
        Keychain.token = response.token
        return response
    }

    func me() async throws -> User {
        let response: UserResponse = try await send(.get, "me")
        return response.user
    }

    func logout() async {
        _ = try? await sendVoid(.post, "logout")
        Keychain.token = nil
    }

    // MARK: - Overview

    func today() async throws -> TodayResponse { try await send(.get, "today") }

    func day(_ date: String) async throws -> TodayResponse { try await send(.get, "days/\(date)") }

    func startDay(_ date: String, carryOverIds: [Int], newTitles: [String]) async throws -> TodoList {
        let response: ListResponse = try await send(.post, "days/\(date)/start", body: [
            "carry_over_ids": carryOverIds,
            "new_titles": newTitles,
        ])
        return response.list
    }

    // MARK: - Lists

    func lists() async throws -> [ListSummary] {
        let response: ListsResponse = try await send(.get, "lists")
        return response.lists
    }

    func master() async throws -> TodoList {
        let response: ListResponse = try await send(.get, "master")
        return response.list
    }

    func list(_ id: Int) async throws -> TodoList {
        let response: ListResponse = try await send(.get, "lists/\(id)")
        return response.list
    }

    func createList(name: String) async throws -> TodoList {
        let response: ListResponse = try await send(.post, "lists", body: ["name": name])
        return response.list
    }

    func renameList(_ id: Int, name: String) async throws -> TodoList {
        let response: ListResponse = try await send(.patch, "lists/\(id)", body: ["name": name])
        return response.list
    }

    func deleteList(_ id: Int) async throws {
        try await sendVoid(.delete, "lists/\(id)")
    }

    func reorder(listId: Int, todoIds: [Int]) async throws -> TodoList {
        let response: ListResponse = try await send(.post, "lists/\(listId)/reorder", body: ["todo_ids": todoIds])
        return response.list
    }

    func setSortMode(listId: Int, mode: String, visibleTodoIds: [Int]?) async throws -> TodoList {
        var body: [String: Any] = ["sort_mode": mode]
        if let visibleTodoIds {
            body["visible_todo_ids"] = visibleTodoIds
        }
        let response: ListResponse = try await send(.post, "lists/\(listId)/sort-mode", body: body)
        return response.list
    }

    // MARK: - Todos

    func createTodo(title: String, listId: Int? = nil, priority: String? = nil) async throws -> Todo {
        var body: [String: Any] = ["title": title]
        if let listId { body["list_id"] = listId }
        if let priority { body["priority"] = priority }
        let response: TodoResponse = try await send(.post, "todos", body: body)
        return response.todo
    }

    func updateTodo(_ id: Int, title: String? = nil, description: String? = nil, priority: String? = nil) async throws -> Todo {
        var body: [String: Any] = [:]
        if let title { body["title"] = title }
        if let description { body["description"] = description }
        if let priority { body["priority"] = priority }
        let response: TodoResponse = try await send(.patch, "todos/\(id)", body: body)
        return response.todo
    }

    func deleteTodo(_ id: Int) async throws {
        try await sendVoid(.delete, "todos/\(id)")
    }

    func complete(_ id: Int) async throws -> Todo {
        let response: TodoResponse = try await send(.post, "todos/\(id)/complete")
        return response.todo
    }

    func uncomplete(_ id: Int) async throws -> Todo {
        let response: TodoResponse = try await send(.post, "todos/\(id)/uncomplete")
        return response.todo
    }

    func addToToday(_ id: Int) async throws -> Todo {
        let response: TodoResponse = try await send(.post, "todos/\(id)/add-to-today")
        return response.todo
    }

    func addToList(_ id: Int, listId: Int) async throws -> Todo {
        let response: TodoResponse = try await send(.post, "todos/\(id)/lists/\(listId)")
        return response.todo
    }

    func removeFromList(_ id: Int, listId: Int) async throws -> Todo {
        let response: TodoResponse = try await send(.delete, "todos/\(id)/lists/\(listId)")
        return response.todo
    }

    func duplicate(_ id: Int, listId: Int? = nil) async throws -> Todo {
        var body: [String: Any] = [:]
        if let listId { body["list_id"] = listId }
        let response: TodoResponse = try await send(.post, "todos/\(id)/duplicate", body: body)
        return response.todo
    }

    func moveToDate(_ id: Int, date: String, fromListId: Int? = nil) async throws -> Todo {
        var body: [String: Any] = ["date": date]
        if let fromListId { body["from_list_id"] = fromListId }
        let response: TodoResponse = try await send(.post, "todos/\(id)/move-to-date", body: body)
        return response.todo
    }

    func syncTags(_ id: Int, tagIds: [Int]) async throws -> Todo {
        let response: TodoResponse = try await send(.patch, "todos/\(id)/tags", body: ["tag_ids": tagIds])
        return response.todo
    }

    @discardableResult
    func quickAdd(title: String) async throws -> QuickAddResponse {
        try await send(.post, "quick-add", body: ["title": title])
    }

    // MARK: - Sub-todos

    func createSubTodo(todoId: Int, title: String) async throws -> Todo {
        let response: TodoResponse = try await send(.post, "todos/\(todoId)/sub-todos", body: ["title": title])
        return response.todo
    }

    func toggleSubTodo(_ id: Int) async throws -> Todo {
        let response: TodoResponse = try await send(.post, "sub-todos/\(id)/toggle")
        return response.todo
    }

    func updateSubTodo(_ id: Int, title: String) async throws -> Todo {
        let response: TodoResponse = try await send(.patch, "sub-todos/\(id)", body: ["title": title])
        return response.todo
    }

    func deleteSubTodo(_ id: Int) async throws -> Todo {
        let response: TodoResponse = try await send(.delete, "sub-todos/\(id)")
        return response.todo
    }

    // MARK: - Tags

    func tags() async throws -> [Tag] {
        let response: TagsResponse = try await send(.get, "tags")
        return response.tags
    }

    func createTag(name: String, color: String? = nil) async throws -> Tag {
        var body: [String: Any] = ["name": name]
        if let color { body["color"] = color }
        let response: TagResponse = try await send(.post, "tags", body: body)
        return response.tag
    }

    func deleteTag(_ id: Int) async throws {
        try await sendVoid(.delete, "tags/\(id)")
    }

    // MARK: - Request plumbing

    @discardableResult
    private func send<T: Decodable>(_ method: Method, _ path: String, body: [String: Any]? = nil, authenticated: Bool = true) async throws -> T {
        let data = try await perform(method, path, body: body, authenticated: authenticated)
        do {
            return try decoder.decode(T.self, from: data)
        } catch {
            throw APIError.decoding(String(describing: error))
        }
    }

    private func sendVoid(_ method: Method, _ path: String, body: [String: Any]? = nil, authenticated: Bool = true) async throws {
        _ = try await perform(method, path, body: body, authenticated: authenticated)
    }

    private func perform(_ method: Method, _ path: String, body: [String: Any]?, authenticated: Bool) async throws -> Data {
        let request = try makeRequest(method, path, body: body, authenticated: authenticated)

        let data: Data
        let response: URLResponse
        do {
            (data, response) = try await urlSession.data(for: request)
        } catch {
            throw APIError.network(error.localizedDescription)
        }

        guard let http = response as? HTTPURLResponse else {
            throw APIError.network("Geen geldig antwoord van de server.")
        }

        switch http.statusCode {
        case 200...299:
            return data
        case 401:
            throw APIError.unauthorized
        case 422:
            throw APIError.validation(Self.validationMessages(from: data))
        default:
            throw APIError.server(http.statusCode, Self.message(from: data))
        }
    }

    private func makeRequest(_ method: Method, _ path: String, body: [String: Any]?, authenticated: Bool) throws -> URLRequest {
        var base = AppConfig.apiBaseURL.absoluteString
        while base.hasSuffix("/") {
            base.removeLast()
        }
        guard let url = URL(string: "\(base)/api/\(path)") else {
            throw APIError.network("Ongeldige API-URL.")
        }

        var request = URLRequest(url: url)
        request.httpMethod = method.rawValue
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        if authenticated {
            guard let token = Keychain.token else {
                throw APIError.notLoggedIn
            }
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        if let body {
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
        }

        return request
    }

    private static func validationMessages(from data: Data) -> [String] {
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            return []
        }
        if let errors = json["errors"] as? [String: Any] {
            return errors.values.compactMap { ($0 as? [Any])?.first as? String }
        }
        if let message = json["message"] as? String {
            return [message]
        }
        return []
    }

    private static func message(from data: Data) -> String? {
        guard let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] else {
            return nil
        }
        return json["message"] as? String
    }
}

#if DEBUG
/// Accepts the self-signed certificate that Laravel Herd serves for `*.test`
/// domains, so the app talks to a local backend during development without
/// installing the Herd CA into the simulator. Compiled out of release builds.
final class DevTrustDelegate: NSObject, URLSessionDelegate {
    func urlSession(
        _ session: URLSession,
        didReceive challenge: URLAuthenticationChallenge,
        completionHandler: @escaping (URLSession.AuthChallengeDisposition, URLCredential?) -> Void
    ) {
        let host = challenge.protectionSpace.host
        if host.hasSuffix(".test"),
           challenge.protectionSpace.authenticationMethod == NSURLAuthenticationMethodServerTrust,
           let trust = challenge.protectionSpace.serverTrust {
            completionHandler(.useCredential, URLCredential(trust: trust))
        } else {
            completionHandler(.performDefaultHandling, nil)
        }
    }
}
#endif
