import Foundation

// MARK: - Domain models

struct User: Codable, Hashable, Identifiable {
    let id: Int
    let name: String
    let email: String
}

struct Tag: Codable, Hashable, Identifiable {
    let id: Int
    let name: String
    let color: String?
}

struct SubTodo: Codable, Hashable, Identifiable {
    let id: Int
    var title: String
    var completedAt: Date?
    var position: Int

    var isCompleted: Bool { completedAt != nil }
}

struct ListMembership: Codable, Hashable, Identifiable {
    let id: Int
    let type: String
    let label: String
}

enum Priority: String, CaseIterable, Identifiable {
    case low
    case normal
    case high

    var id: String { rawValue }

    var label: String {
        switch self {
        case .low: return "Laag"
        case .normal: return "Normaal"
        case .high: return "Hoog"
        }
    }
}

struct Todo: Codable, Hashable, Identifiable {
    let id: Int
    var title: String
    var description: String?
    var priority: String
    var completedAt: Date?
    var createdAt: Date?
    var tags: [Tag]?
    var position: Int?
    var listMemberships: [ListMembership]?
    var subTodos: [SubTodo]?

    var isCompleted: Bool { completedAt != nil }
    var priorityValue: Priority { Priority(rawValue: priority) ?? .normal }
    var openSubTodoCount: Int { (subTodos ?? []).filter { !$0.isCompleted }.count }
    var totalSubTodoCount: Int { (subTodos ?? []).count }

    /// Custom/daily list memberships, excluding the implicit master list.
    var otherMemberships: [ListMembership] { listMemberships ?? [] }
}

struct TodoList: Codable, Hashable, Identifiable {
    let id: Int
    let type: String
    var name: String?
    var date: String?
    var sortMode: String
    var todos: [Todo]?

    var isMaster: Bool { type == "master" }
    var isDaily: Bool { type == "daily" }
    var isCustom: Bool { type == "custom" }

    var displayName: String {
        switch type {
        case "master": return "Alles"
        case "daily": return date ?? "Dag"
        default: return name ?? "Lijst"
        }
    }
}

struct ListSummary: Codable, Hashable, Identifiable {
    let id: Int
    let type: String
    var name: String?
    var date: String?
    var sortMode: String
    var openCount: Int
    var totalCount: Int

    var isMaster: Bool { type == "master" }

    var displayName: String {
        switch type {
        case "master": return "Alles"
        default: return name ?? "Lijst"
        }
    }
}

// MARK: - Response envelopes

struct LoginResponse: Codable { let token: String; let user: User }
struct UserResponse: Codable { let user: User }
struct TodoResponse: Codable { let todo: Todo }
struct ListResponse: Codable { let list: TodoList }
struct ListsResponse: Codable { let lists: [ListSummary] }
struct TagResponse: Codable { let tag: Tag }
struct TagsResponse: Codable { let tags: [Tag] }
struct QuickAddResponse: Codable { let todo: Todo; let targetDate: String }

struct TodayResponse: Codable {
    let date: String
    let isToday: Bool
    let list: TodoList?
    let needsRitual: Bool
    let previousWorkday: String
    let carryOverCandidates: [Todo]
    let masterOpenTodos: [Todo]
    let preScheduled: [Todo]
}
