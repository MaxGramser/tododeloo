import Observation
import SwiftUI

/// Backs the "Binnenkort" agenda: the days ahead that hold scheduled todos,
/// oldest first. Owns loading and the handful of triage actions that make sense
/// from a schedule overview — toggle done, reschedule, pull to today, delete —
/// each reconciled against the server. The full per-todo menu lives on the day
/// board you tap through to.
@MainActor
@Observable
final class UpcomingModel {
    /// Daily lists, each carrying its `date` and `todos`, ordered by date.
    var days: [TodoList] = []

    var isLoading = false
    var hasLoaded = false
    var errorMessage: String?

    private let api = APIClient.shared
    var onUnauthorized: (() -> Void)?

    var isEmpty: Bool { days.allSatisfy { ($0.todos ?? []).isEmpty } }

    func load() async {
        isLoading = true
        defer { isLoading = false; hasLoaded = true }
        do {
            days = try await api.upcoming()
            errorMessage = nil
        } catch {
            handle(error)
        }
    }

    // MARK: - Triage actions

    func toggle(_ todo: Todo) async {
        let nowCompleted = !todo.isCompleted
        mutate(todo.id) { $0.completedAt = nowCompleted ? Date() : nil }
        do {
            _ = nowCompleted ? try await api.complete(todo.id) : try await api.uncomplete(todo.id)
            await load()
        } catch {
            handle(error)
            await load()
        }
    }

    func move(_ todo: Todo, toDate date: String) async {
        do {
            _ = try await api.moveToDate(todo.id, date: date, fromListId: dayListId(of: todo))
            await load()
        } catch {
            handle(error)
            await load()
        }
    }

    func addToToday(_ todo: Todo) async {
        do {
            _ = try await api.addToToday(todo.id)
            await load()
        } catch {
            handle(error)
            await load()
        }
    }

    func delete(_ todo: Todo) async {
        remove(todo.id)
        do {
            try await api.deleteTodo(todo.id)
            await load()
        } catch {
            handle(error)
            await load()
        }
    }

    // MARK: - Helpers

    /// The daily list a todo currently sits on, so a move can unlink it there.
    private func dayListId(of todo: Todo) -> Int? {
        days.first { ($0.todos ?? []).contains { $0.id == todo.id } }?.id
    }

    private func mutate(_ todoID: Int, _ change: (inout Todo) -> Void) {
        for dayIndex in days.indices {
            guard var todos = days[dayIndex].todos,
                  let todoIndex = todos.firstIndex(where: { $0.id == todoID }) else { continue }
            change(&todos[todoIndex])
            days[dayIndex].todos = todos
            return
        }
    }

    private func remove(_ todoID: Int) {
        for dayIndex in days.indices {
            days[dayIndex].todos?.removeAll { $0.id == todoID }
        }
    }

    private func handle(_ error: Error) {
        if case APIError.unauthorized = error {
            onUnauthorized?()
            return
        }
        errorMessage = (error as? APIError)?.errorDescription ?? error.localizedDescription
    }
}
