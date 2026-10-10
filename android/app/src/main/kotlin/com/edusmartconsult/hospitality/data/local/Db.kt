package com.edusmartconsult.hospitality.data.local

import android.content.Context
import androidx.room.Dao
import androidx.room.Database
import androidx.room.Entity
import androidx.room.Index
import androidx.room.Insert
import androidx.room.PrimaryKey
import androidx.room.Query
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.Upsert
import kotlinx.coroutines.flow.Flow

/**
 * Every synced record is stored as JSON keyed by (entity, id). The server's schema is tenant-configurable
 * (custom fields, options), so a schema-less local store keeps the app compatible as companies customise.
 */
@Entity(tableName = "docs", primaryKeys = ["entity", "id"], indices = [Index("entity")])
data class DocEntity(val entity: String, val id: String, val json: String, val updatedAt: String, val pending: Boolean = false)

@Entity(tableName = "outbox")
data class OutboxEntity(
    @PrimaryKey(autoGenerate = true) val seq: Long = 0,
    val itemId: String,
    val entity: String,
    val op: String,
    val payload: String,
    val idempotencyKey: String,
    val createdAt: Long,
    val status: String = "pending", // pending | failed
    val error: String? = null,
    val attempts: Int = 0,
)

@Entity(tableName = "kv")
data class KvEntity(@PrimaryKey val key: String, val value: String)

@Dao
interface DocDao {
    @Query("SELECT * FROM docs WHERE entity = :entity")
    fun observe(entity: String): Flow<List<DocEntity>>

    @Query("SELECT * FROM docs WHERE entity = :entity")
    suspend fun all(entity: String): List<DocEntity>

    @Query("SELECT * FROM docs WHERE entity = :entity AND id = :id")
    suspend fun get(entity: String, id: String): DocEntity?

    @Upsert
    suspend fun upsert(docs: List<DocEntity>)

    @Upsert
    suspend fun upsert(doc: DocEntity)

    @Query("DELETE FROM docs WHERE entity = :entity AND id IN (:ids)")
    suspend fun delete(entity: String, ids: List<String>)

    @Query("DELETE FROM docs")
    suspend fun clear()
}

@Dao
interface OutboxDao {
    @Insert
    suspend fun insert(item: OutboxEntity): Long

    @Query("SELECT * FROM outbox WHERE status = 'pending' ORDER BY seq LIMIT :limit")
    suspend fun pending(limit: Int): List<OutboxEntity>

    @Query("SELECT * FROM outbox ORDER BY seq")
    fun observeAll(): Flow<List<OutboxEntity>>

    @Query("SELECT COUNT(*) FROM outbox WHERE status = 'pending'")
    fun observePendingCount(): Flow<Int>

    @Query("SELECT COUNT(*) FROM outbox WHERE status = 'failed'")
    fun observeFailedCount(): Flow<Int>

    @Query("DELETE FROM outbox WHERE seq IN (:seqs)")
    suspend fun delete(seqs: List<Long>)

    @Query("UPDATE outbox SET status = 'failed', error = :error, attempts = attempts + 1 WHERE seq = :seq")
    suspend fun fail(seq: Long, error: String)

    @Query("UPDATE outbox SET status = 'pending', error = NULL WHERE seq = :seq")
    suspend fun retry(seq: Long)

    @Query("UPDATE outbox SET attempts = attempts + 1 WHERE seq IN (:seqs)")
    suspend fun bump(seqs: List<Long>)

    @Query("SELECT * FROM outbox WHERE seq = :seq")
    suspend fun get(seq: Long): OutboxEntity?

    @Query("DELETE FROM outbox")
    suspend fun clear()
}

@Dao
interface KvDao {
    @Query("SELECT value FROM kv WHERE `key` = :key")
    suspend fun get(key: String): String?

    @Upsert
    suspend fun put(kv: KvEntity)

    @Query("DELETE FROM kv")
    suspend fun clear()
}

@Database(entities = [DocEntity::class, OutboxEntity::class, KvEntity::class], version = 1, exportSchema = false)
abstract class AppDb : RoomDatabase() {
    abstract fun docs(): DocDao
    abstract fun outbox(): OutboxDao
    abstract fun kv(): KvDao

    companion object {
        fun create(context: Context): AppDb = Room.databaseBuilder(context, AppDb::class.java, "hospitality.db").fallbackToDestructiveMigration().build()
    }
}
